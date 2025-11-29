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
        // Query completa que busca os dados para a Home
        $sql = "select 
                    c.id_usuario,
                    c.nm_usuario || ' ' || c.nm_sobrenome as nm_usuario_anfitriao,
                    a.id_cardapio,
                    a.ds_cardapio as nm_cardapio,
                    a.preco_refeicao,
                    d.hr_encontro,
                    d.nu_max_convidados,
                    a.id_local,
                    b.nu_cep,
                    b.nu_casa
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

    // --- NOVA FUNÇÃO PODEROSA ---
    public function createJantarCompleto($dados) {
        try {
            $this->conexao->beginTransaction(); // Inicia transação para segurança

            // 1. CRIAR LOCAL
            // Gera ID Local
            $sqlSeqL = "select id_sequence from tb_sequence_dn order by id_sequence desc limit 1";
            $resSeq = $this->conexao->query($sqlSeqL)->fetch(PDO::FETCH_ASSOC);
            $idLocal = ($resSeq ? $resSeq['id_sequence'] : 0) + 1;
            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idLocal, 'L')");

            // Insere Local
            $sqlLocal = "INSERT INTO tb_local_dn (id_local, id_usuario, nu_cep, nu_casa) VALUES (:id, :user, :cep, :num)";
            $stmtL = $this->conexao->prepare($sqlLocal);
            $stmtL->execute([
                ':id' => $idLocal,
                ':user' => $dados['id_usuario'],
                ':cep' => $dados['nu_cep'],
                ':num' => $dados['nu_casa']
            ]);

            // 2. CRIAR CARDAPIO
            // Gera ID Cardapio
            $idCardapio = $idLocal + 1; // Simplificação da sequence, ideal seria consultar de novo, mas ok para agora
            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idCardapio, 'C')");

            // Insere Cardapio (Com Preço!)
            $sqlCard = "INSERT INTO tb_cardapio_dn (id_cardapio, id_local, nm_cardapio, ds_cardapio, preco_refeicao) VALUES (:id, :loc, :nome, :desc, :preco)";
            $stmtC = $this->conexao->prepare($sqlCard);
            $stmtC->execute([
                ':id' => $idCardapio,
                ':loc' => $idLocal,
                ':nome' => $dados['nm_cardapio'], // Título
                ':desc' => $dados['ds_cardapio'], // Descrição
                ':preco' => $dados['preco_refeicao']
            ]);

            // 3. CRIAR ENCONTRO
            // Gera ID Encontro
            $idEncontro = $idCardapio + 1;
            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idEncontro, 'E')");

            // Insere Encontro
            $sqlEnc = "INSERT INTO tb_encontro_dn (id_encontro, id_local, id_cardapio, hr_encontro, nu_max_convidados, fl_anfitriao_confirma) VALUES (:id, :loc, :card, :hora, :vagas, 'true')";
            $stmtE = $this->conexao->prepare($sqlEnc);
            $stmtE->execute([
                ':id' => $idEncontro,
                ':loc' => $idLocal,
                ':card' => $idCardapio,
                ':hora' => $dados['hr_encontro'],
                ':vagas' => $dados['nu_max_convidados']
            ]);

            $this->conexao->commit(); // Salva tudo
            $this->banco->setDados(1, ["Mensagem" => "Jantar criado com sucesso!"]);

        } catch (Exception $e) {
            $this->conexao->rollBack(); // Desfaz se der erro
            throw new Exception("Erro ao criar jantar: " . $e->getMessage());
        }
    }
}
?>