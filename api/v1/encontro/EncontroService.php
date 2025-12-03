<?php
require_once('../../database/InstanciaBanco.php');

class EncontroService extends InstanciaBanco {

    public function addUsuarioEncontro($id_usuario, $id_encontro, $nu_dependentes) {
        // ... (código existente de adicionar reserva) ...
        // 1. Verifica se já reservou
        $check = $this->conexao->query("SELECT * FROM tb_encontro_usuario_dn WHERE id_usuario = $id_usuario AND id_encontro = $id_encontro");
        if ($check->rowCount() > 0) {
            throw new Exception("Você já fez uma reserva para este jantar.");
        }

        // 2. TRAVA DE SEGURANÇA: Verifica Lotação
        $sqlCapacidade = "
            SELECT 
                e.nu_max_convidados,
                (SELECT COALESCE(SUM(1 + eu.nu_dependentes), 0) 
                 FROM tb_encontro_usuario_dn eu 
                 WHERE eu.id_encontro = e.id_encontro) as total_atual
            FROM tb_encontro_dn e
            WHERE e.id_encontro = :id
        ";
        $stmtCap = $this->conexao->prepare($sqlCapacidade);
        $stmtCap->execute([':id' => $id_encontro]);
        $dados = $stmtCap->fetch(PDO::FETCH_ASSOC);

        if (!$dados) throw new Exception("Encontro não encontrado.");

        $max = $dados['nu_max_convidados'];
        $atual = $dados['total_atual'];
        $novos = 1 + $nu_dependentes;

        if (($atual + $novos) > $max) {
            throw new Exception("Não há vagas suficientes.");
        }

        // 3. Insere
        $sql = "INSERT INTO tb_encontro_usuario_dn (id_usuario, id_encontro, nu_dependentes, fl_anfitriao) 
                VALUES (:id_usuario, :id_encontro, :deps, 'false')";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':id_encontro', $id_encontro, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':deps', $nu_dependentes, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->banco->setDados(1, ["Mensagem" => "Reserva confirmada com sucesso!"]);
        } else {
            throw new Exception("Erro ao salvar reserva no banco.");
        }
    }

    // --- NOVA FUNÇÃO 1: VERIFICAR SE JÁ RESERVEI ---
    public function verificarReserva($id_usuario, $id_encontro) {
        $sql = "SELECT * FROM tb_encontro_usuario_dn WHERE id_usuario = :user AND id_encontro = :encontro";
        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([':user' => $id_usuario, ':encontro' => $id_encontro]);
        
        if ($stmt->rowCount() > 0) {
            $this->banco->setDados(1, ["ja_reservou" => true]);
        } else {
            $this->banco->setDados(0, ["ja_reservou" => false]);
        }
    }

    // --- NOVA FUNÇÃO 2: CANCELAR MINHA RESERVA ---
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

    // ... (Mantenha getMinhasReservas e getMeusJantaresCriados iguais) ...
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
                    u.vl_foto,
                    (SELECT COALESCE(SUM(1 + eu_count.nu_dependentes), 0)
                     FROM tb_encontro_usuario_dn eu_count
                     WHERE eu_count.id_encontro = e.id_encontro) as nu_convidados_confirmados
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