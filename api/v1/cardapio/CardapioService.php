<?php
require_once('../../database/InstanciaBanco.php');

class CardapioService extends InstanciaBanco {
    
    // ... (Mantenha getCardapio e getCardapios iguais) ...
    public function getCardapio() {
        $sql = "SELECT * from tb_cardapio_dn where id_cardapio = ".$_GET['id_cardapio'];
        $consulta = $this->conexao->query($sql);
        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
        return $resultados;
    }

    public function getCardapiosDisponiveis() {
        $sql = "select 
                    c.id_usuario,
                    c.nm_usuario || ' ' || c.nm_sobrenome as nm_usuario_anfitriao,
                    c.vl_foto as vl_foto_usuario,
                    a.id_cardapio,
                    a.nm_cardapio,
                    a.ds_cardapio,
                    a.preco_refeicao,
                    a.vl_foto_cardapio,
                    d.hr_encontro,
                    d.nu_max_convidados,
                    d.id_encontro,
                    a.id_local,
                    b.nu_cep,
                    b.nu_casa,
                    (
                        SELECT COALESCE(SUM(1 + eu.nu_dependentes), 0)
                        FROM tb_encontro_usuario_dn eu
                        WHERE eu.id_encontro = d.id_encontro
                    ) as nu_convidados_confirmados
                from tb_cardapio_dn a 
                inner join tb_local_dn b on a.id_local = b.id_local
                inner join tb_usuario_dn c on b.id_usuario = c.id_usuario
                inner join tb_encontro_dn d on b.id_local = d.id_local
                WHERE d.hr_encontro > now()
                ORDER BY d.hr_encontro ASC";
    
        $consulta = $this->conexao->query($sql);
        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
        return $resultados;
    }

    public function createJantarCompleto($dados) {
        try {
            $this->conexao->beginTransaction();

            // 1. CRIAR LOCAL (mantém igual)
            $sqlSeqL = "select id_sequence from tb_sequence_dn order by id_sequence desc limit 1";
            $resSeq = $this->conexao->query($sqlSeqL)->fetch(PDO::FETCH_ASSOC);
            $idLocal = ($resSeq ? $resSeq['id_sequence'] : 0) + 1;
            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idLocal, 'L')");

            $sqlLocal = "INSERT INTO tb_local_dn (id_local, id_usuario, nu_cep, nu_casa) VALUES (:id, :user, :cep, :num)";
            $stmtL = $this->conexao->prepare($sqlLocal);
            $stmtL->execute([
                ':id' => $idLocal,
                ':user' => $dados['id_usuario'],
                ':cep' => $dados['nu_cep'],
                ':num' => $dados['nu_casa']
            ]);

            // 2. CRIAR CARDAPIO (AQUI ESTÁ A CORREÇÃO!)
            $idCardapio = $idLocal + 1;
            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idCardapio, 'C')");

            // --- CORREÇÃO: Adicionei vl_foto_cardapio no INSERT ---
            $sqlCard = "INSERT INTO tb_cardapio_dn (id_cardapio, id_local, nm_cardapio, ds_cardapio, preco_refeicao, vl_foto_cardapio) 
                        VALUES (:id, :loc, :nome, :desc, :preco, :foto)";
            
            $stmtC = $this->conexao->prepare($sqlCard);
            $stmtC->execute([
                ':id' => $idCardapio,
                ':loc' => $idLocal,
                ':nome' => $dados['nm_cardapio'],
                ':desc' => $dados['ds_cardapio'],
                ':preco' => $dados['preco_refeicao'],
                ':foto' => $dados['vl_foto'] // <--- O PHP agora salva o link!
            ]);
            // -----------------------------------------------------

            // 3. CRIAR ENCONTRO (mantém igual)
            $idEncontro = $idCardapio + 1;
            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idEncontro, 'E')");

            $sqlEnc = "INSERT INTO tb_encontro_dn (id_encontro, id_local, id_cardapio, hr_encontro, nu_max_convidados, fl_anfitriao_confirma) VALUES (:id, :loc, :card, :hora, :vagas, 'true')";
            $stmtE = $this->conexao->prepare($sqlEnc);
            $stmtE->execute([
                ':id' => $idEncontro,
                ':loc' => $idLocal,
                ':card' => $idCardapio,
                ':hora' => $dados['hr_encontro'],
                ':vagas' => $dados['nu_max_convidados']
            ]);

            $this->conexao->commit();
            $this->banco->setDados(1, ["Mensagem" => "Jantar criado com sucesso!"]);

        } catch (Exception $e) {
            $this->conexao->rollBack();
            throw new Exception("Erro ao criar jantar: " . $e->getMessage());
        }
    }
}
?>