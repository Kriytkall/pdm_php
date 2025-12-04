<?php
require_once('../../database/InstanciaBanco.php');

class EncontroService extends InstanciaBanco {

    public function addUsuarioEncontro($id_usuario, $id_encontro, $nu_dependentes) {
        $check = $this->conexao->query("SELECT * FROM tb_encontro_usuario_dn WHERE id_usuario = $id_usuario AND id_encontro = $id_encontro");
        if ($check->rowCount() > 0) {
            throw new Exception("Você já solicitou reserva para este jantar.");
        }

        $sql = "INSERT INTO tb_encontro_usuario_dn (id_usuario, id_encontro, nu_dependentes, fl_anfitriao, fl_status) 
                VALUES (:id_usuario, :id_encontro, :deps, 'false', 'P')";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':id_encontro', $id_encontro, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':deps', $nu_dependentes, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->banco->setDados(1, ["Mensagem" => "Solicitação enviada! Aguarde a aprovação do anfitrião."]);
        } else {
            throw new Exception("Erro ao salvar solicitação.");
        }
    }

    public function aprovarReserva($id_encontro, $id_usuario_convidado) {
        $sqlCapacidade = "
            SELECT 
                e.nu_max_convidados,
                (SELECT COALESCE(SUM(1 + eu.nu_dependentes), 0) 
                 FROM tb_encontro_usuario_dn eu 
                 WHERE eu.id_encontro = e.id_encontro AND eu.fl_status = 'C') as total_confirmados
            FROM tb_encontro_dn e
            WHERE e.id_encontro = :id
        ";
        $stmtCap = $this->conexao->prepare($sqlCapacidade);
        $stmtCap->execute([':id' => $id_encontro]);
        $dados = $stmtCap->fetch(PDO::FETCH_ASSOC);

        $sqlConv = "SELECT nu_dependentes FROM tb_encontro_usuario_dn WHERE id_encontro = :id AND id_usuario = :user";
        $stmtConv = $this->conexao->prepare($sqlConv);
        $stmtConv->execute([':id' => $id_encontro, ':user' => $id_usuario_convidado]);
        $dadosConv = $stmtConv->fetch(PDO::FETCH_ASSOC);

        $lugaresNecessarios = 1 + ($dadosConv['nu_dependentes'] ?? 0);
        $lugaresOcupados = $dados['total_confirmados'];
        $max = $dados['nu_max_convidados'];

        if (($lugaresOcupados + $lugaresNecessarios) > $max) {
            throw new Exception("Não há vagas suficientes para aprovar este grupo.");
        }

        $sql = "UPDATE tb_encontro_usuario_dn SET fl_status = 'C' WHERE id_encontro = :id AND id_usuario = :user";
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':id' => $id_encontro, ':user' => $id_usuario_convidado]);
        
        $this->banco->setDados(1, ["Mensagem" => "Convidado confirmado com sucesso!"]);
    }

    public function rejeitarReserva($id_encontro, $id_usuario_convidado) {
        $sql = "DELETE FROM tb_encontro_usuario_dn WHERE id_encontro = :id AND id_usuario = :user";
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':id' => $id_encontro, ':user' => $id_usuario_convidado]);
        
        $this->banco->setDados(1, ["Mensagem" => "Solicitação recusada."]);
    }
    
    public function getParticipantes($id_encontro) {
        $sql = "SELECT 
                    u.id_usuario,
                    u.nm_usuario || ' ' || u.nm_sobrenome as nome_completo,
                    u.vl_foto,
                    eu.nu_dependentes,
                    eu.fl_status
                FROM tb_encontro_usuario_dn eu
                INNER JOIN tb_usuario_dn u ON eu.id_usuario = u.id_usuario
                WHERE eu.id_encontro = :id AND eu.fl_anfitriao = 'false'
                ORDER BY eu.fl_status DESC, u.nm_usuario ASC";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':id' => $id_encontro]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->banco->setDados(count($res), $res);
        if (!$res) $this->banco->setDados(0, []);
    }

    public function verificarReserva($id_usuario, $id_encontro) {
        $sql = "SELECT fl_status FROM tb_encontro_usuario_dn WHERE id_usuario = :user AND id_encontro = :encontro";
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':user' => $id_usuario, ':encontro' => $id_encontro]);
        
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            $this->banco->setDados(1, ["ja_reservou" => true, "status" => $res['fl_status']]);
        } else {
            $this->banco->setDados(0, ["ja_reservou" => false, "status" => null]);
        }
    }

    public function deleteUsuarioEncontro($id_usuario, $id_encontro) {
        $sql = "DELETE FROM tb_encontro_usuario_dn WHERE id_usuario = :user AND id_encontro = :encontro";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':user', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':encontro', $id_encontro, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
             $this->banco->setDados(1, ["Mensagem" => "Reserva cancelada."]);
        } else {
             throw new Exception("Erro ao cancelar reserva.");
        }
    }

    public function getMinhasReservas($id_usuario) {
        $sql = "SELECT 
                    c.id_cardapio,
                    c.nm_cardapio,
                    c.ds_cardapio,
                    c.preco_refeicao,
                    c.vl_foto_cardapio,
                    e.id_encontro,
                    e.hr_encontro,
                    e.nu_max_convidados,
                    l.id_local,
                    l.nu_cep,
                    l.nu_casa,
                    u_host.id_usuario,
                    u_host.nm_usuario || ' ' || u_host.nm_sobrenome as nm_usuario_anfitriao,
                    u_host.vl_foto,
                    eu.fl_status,
                    (SELECT COALESCE(SUM(1 + eu_count.nu_dependentes), 0)
                     FROM tb_encontro_usuario_dn eu_count
                     WHERE eu_count.id_encontro = e.id_encontro) as nu_convidados_confirmados
                FROM tb_encontro_usuario_dn eu
                INNER JOIN tb_encontro_dn e ON eu.id_encontro = e.id_encontro
                INNER JOIN tb_cardapio_dn c ON e.id_cardapio = c.id_cardapio
                INNER JOIN tb_local_dn l ON c.id_local = l.id_local
                INNER JOIN tb_usuario_dn u_host ON l.id_usuario = u_host.id_usuario
                WHERE eu.id_usuario = :id_usuario
                ORDER BY e.hr_encontro DESC";

        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
    }

    public function getMeusJantaresCriados($id_usuario) {
        $sql = "SELECT 
                    c.id_cardapio,
                    c.nm_cardapio,
                    c.ds_cardapio,
                    c.preco_refeicao,
                    c.vl_foto_cardapio,
                    e.id_encontro,
                    e.hr_encontro,
                    e.nu_max_convidados,
                    l.id_local,
                    l.nu_cep,
                    l.nu_casa,
                    u.id_usuario,
                    u.nm_usuario || ' ' || u.nm_sobrenome as nm_usuario_anfitriao,
                    u.vl_foto as vl_foto_usuario,
                    (SELECT COALESCE(SUM(1 + eu_count.nu_dependentes), 0)
                     FROM tb_encontro_usuario_dn eu_count
                     WHERE eu_count.id_encontro = e.id_encontro AND eu_count.fl_status = 'C') as nu_convidados_confirmados,
                    (SELECT COUNT(*)
                     FROM tb_encontro_usuario_dn eu_pend
                     WHERE eu_pend.id_encontro = e.id_encontro AND eu_pend.fl_status = 'P') as nu_solicitacoes_pendentes
                FROM tb_cardapio_dn c
                INNER JOIN tb_local_dn l ON c.id_local = l.id_local
                INNER JOIN tb_encontro_dn e ON c.id_cardapio = e.id_cardapio
                INNER JOIN tb_usuario_dn u ON l.id_usuario = u.id_usuario
                WHERE l.id_usuario = :id_usuario
                ORDER BY e.hr_encontro DESC";

        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
    }
}
?>