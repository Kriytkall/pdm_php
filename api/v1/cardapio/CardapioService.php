<?php
require_once('../../database/InstanciaBanco.php');

class CardapioService extends InstanciaBanco {
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

            $idLocal = 0;
            if (isset($dados['id_local']) && !empty($dados['id_local']) && $dados['id_local'] != 'novo') {
                $idLocal = $dados['id_local'];
            } 
            else {
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
            }
            
            $idCardapio = $idLocal + 1; 
            $sqlSeqC = "select id_sequence from tb_sequence_dn order by id_sequence desc limit 1";
            $resSeqC = $this->conexao->query($sqlSeqC)->fetch(PDO::FETCH_ASSOC);
            $idCardapio = ($resSeqC ? $resSeqC['id_sequence'] : 0) + 1;

            $this->conexao->query("INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES ($idCardapio, 'C')");

            $sqlCard = "INSERT INTO tb_cardapio_dn (id_cardapio, id_local, nm_cardapio, ds_cardapio, preco_refeicao, vl_foto_cardapio) VALUES (:id, :loc, :nome, :desc, :preco, :foto)";
            $stmtC = $this->conexao->prepare($sqlCard);
            $stmtC->execute([
                ':id' => $idCardapio,
                ':loc' => $idLocal,
                ':nome' => $dados['nm_cardapio'],
                ':desc' => $dados['ds_cardapio'],
                ':preco' => $dados['preco_refeicao'],
                ':foto' => $dados['vl_foto']
            ]);

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

    public function updateJantar($dados) {
        try {
            $this->conexao->beginTransaction();

            $sqlC = "UPDATE tb_cardapio_dn SET 
                        nm_cardapio = :nome, 
                        ds_cardapio = :desc, 
                        preco_refeicao = :preco,
                        vl_foto_cardapio = :foto 
                     WHERE id_cardapio = :id";
            
            $stmtC = $this->conexao->prepare($sqlC);
            $stmtC->execute([
                ':nome' => $dados['nm_cardapio'],
                ':desc' => $dados['ds_cardapio'],
                ':preco' => $dados['preco_refeicao'],
                ':foto' => $dados['vl_foto'],
                ':id' => $dados['id_cardapio']
            ]);

            $sqlE = "UPDATE tb_encontro_dn SET 
                        hr_encontro = :hora, 
                        nu_max_convidados = :vagas 
                     WHERE id_cardapio = :idCardapio";
            
            $stmtE = $this->conexao->prepare($sqlE);
            $stmtE->execute([
                ':hora' => $dados['hr_encontro'],
                ':vagas' => $dados['nu_max_convidados'],
                ':idCardapio' => $dados['id_cardapio']
            ]);

            $sqlL = "UPDATE tb_local_dn SET 
                        nu_cep = :cep, 
                        nu_casa = :num 
                     WHERE id_local = (SELECT id_local FROM tb_cardapio_dn WHERE id_cardapio = :idCard)";
            
            $stmtL = $this->conexao->prepare($sqlL);
            $stmtL->execute([
                ':cep' => $dados['nu_cep'],
                ':num' => $dados['nu_casa'],
                ':idCard' => $dados['id_cardapio']
            ]);

            $this->conexao->commit();
            $this->banco->setDados(1, ["Mensagem" => "Jantar atualizado com sucesso!"]);

        } catch (Exception $e) {
            $this->conexao->rollBack();
            throw new Exception("Erro ao atualizar: " . $e->getMessage());
        }
    }

    public function deleteJantar($idCardapio) {
        try {
            $this->conexao->beginTransaction();

            $sqlIds = "SELECT id_local, id_encontro FROM tb_encontro_dn WHERE id_cardapio = :id";
            $stmt = $this->conexao->prepare($sqlIds);
            $stmt->execute([':id' => $idCardapio]);
            $ids = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ids) {
                $idEncontro = $ids['id_encontro'];

                $this->conexao->exec("DELETE FROM tb_encontro_usuario_dn WHERE id_encontro = $idEncontro");

                $this->conexao->exec("DELETE FROM tb_encontro_dn WHERE id_encontro = $idEncontro");

                $this->conexao->exec("DELETE FROM tb_cardapio_dn WHERE id_cardapio = $idCardapio");
            }

            $this->conexao->commit();
            $this->banco->setDados(1, ["Mensagem" => "Jantar cancelado e excluído."]);

        } catch (Exception $e) {
            $this->conexao->rollBack();
            throw new Exception("Erro ao excluir: " . $e->getMessage());
        }
    }
}
?>