<?php
require_once('../../database/InstanciaBanco.php');

class LocalService extends InstanciaBanco {
    
    public function getLocal() {
        $sql = "SELECT * from tb_local_dn where id_local = ".$_GET['id_local'];
        $consulta = $this->conexao->query($sql);
        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
        return $resultados;
    }

    public function getLocais() {
        $sql = "SELECT * from tb_local_dn";
        $consulta = $this->conexao->query($sql);
        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
        return $resultados;
    }

    // --- A FUNÇÃO QUE BUSCA OS LOCAIS DO USUÁRIO ---
    public function getMeusLocais() {
        $id_usuario = $_GET['id_usuario'];
        
        // Ordena pelo ID decrescente para o último criado aparecer primeiro
        $sql = "SELECT * FROM tb_local_dn WHERE id_usuario = " . $id_usuario . " ORDER BY id_local DESC";

        $consulta = $this->conexao->query($sql);
        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
        
        $this->banco->setDados(count($resultados), $resultados);

        // Se não achar nada, retorna lista vazia (não null)
        if (!$resultados) {
            $this->banco->setDados(0, []);
        }
        
        return $resultados;
    }
    // ------------------------------------------------

    public function createLocal($nu_cep, $nu_casa, $id_usuario, $nu_cnpj, $dc_complemento) {
        $sql = "select id_sequence from tb_sequence_dn order by id_sequence desc limit 1;";
        $consulta = $this->conexao->query($sql);
        $maiorid = $consulta->fetchAll(PDO::FETCH_ASSOC);

        if (!$maiorid){
            $maiorid = 1;
        } else {
            $maiorid = $maiorid[0]['id_sequence'] + 1;
        }
        
        $sqlseq ="INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES (".$maiorid.", 'L')";
        $this->conexao->query($sqlseq);
        
        $sql = "INSERT INTO tb_local_dn (id_local, id_usuario, nu_cep, nu_casa, nu_cnpj, dc_complemento) VALUES (:id_local, :id_usuario, :nu_cep, :nu_casa, :nu_cnpj, :dc_complemento)";

        $insertlocal = $this->conexao->prepare($sql);

        $insertlocal->bindValue(':id_local', $maiorid, PDO::PARAM_INT);
        $insertlocal->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $insertlocal->bindValue(':nu_cnpj', $nu_cnpj, PDO::PARAM_STR);
        $insertlocal->bindValue(':nu_cep', $nu_cep, PDO::PARAM_STR);
        $insertlocal->bindValue(':nu_casa', $nu_casa, PDO::PARAM_STR);
        $insertlocal->bindValue(':dc_complemento', $dc_complemento, PDO::PARAM_STR);
        $resultados = $insertlocal->execute();

        // Retorna True ou o próprio objeto criado para confirmação
        if ($resultados) {
            $this->banco->setDados(1, ["Mensagem" => "Local criado com sucesso"]);
        } else {
            throw new Exception("Não foi possível criar o local");
        }
        return $resultados;
    }
    
    public function deleteLocal($id_local) {
        // Limpeza de dependências (Cascata manual caso o banco não tenha)
        $this->conexao->query("DELETE FROM tb_encontro_dn WHERE id_local = $id_local");
        $this->conexao->query("DELETE FROM tb_cardapio_dn WHERE id_local = $id_local");

        $sql = "DELETE FROM tb_local_dn WHERE id_local = ".$id_local;
        $deleteuser = $this->conexao->query($sql);
        
        $this->banco->setMensagem(1, "Deletado com sucesso");
    }
}
?>