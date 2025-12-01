<?php

require_once('../../database/InstanciaBanco.php');

class UsuarioService extends InstanciaBanco {
    
    // Lista todos os usuários
    public function getUsuarios() {
        $sql = "SELECT * FROM tb_usuario_dn";
        $consulta = $this->conexao->query($sql);
        $retorno = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($retorno), $retorno);
        if (!$retorno) { throw new Exception("Usuario nao Localizado"); }
        return $retorno;
    }

    // Busca um usuário específico pelo ID
    public function getUsuario() {
        $sql = "SELECT * FROM tb_usuario_dn where id_usuario = ".$_GET['id_usuario'];
        $consulta = $this->conexao->query($sql);
        $ret = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(0, $ret);
        if (!$ret) { throw new Exception("Usuario nao Localizado"); }
        return $ret;
    }

    // Cria um novo usuário (Cadastro)
    public function createUsuario($nu_cpf, $nm_usuario, $vl_email, $nm_sobrenome, $vl_senha, $vl_foto = null) {
        
        // 1. Verifica se já existe CPF ou Email
        $sqlCheck = "SELECT COUNT(*) as total FROM tb_usuario_dn WHERE nu_cpf = :nu_cpf OR vl_email = :vl_email";
        $stmtCheck = $this->conexao->prepare($sqlCheck);
        $stmtCheck->execute([':nu_cpf' => $nu_cpf,':vl_email' => $vl_email]);

        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($row['total'] > 0) {
            throw new Exception ("Já existe um usuário com este CPF ou email.");
        }

        // 2. Gera o próximo ID usando a tabela de sequência
        $sql = "select id_sequence from tb_sequence_dn order by id_sequence desc limit 1;";
        $consulta = $this->conexao->query($sql);
        $maiorid = $consulta->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$maiorid){
            $maiorid = 1;
        } else {
            $maiorid = $maiorid[0]['id_sequence'] + 1;
        }

        // Atualiza a sequência
        $sqlseq ="INSERT INTO tb_sequence_dn (id_sequence, nm_sequence) VALUES (".$maiorid.", 'U')";
        $insertseq = $this->conexao->query($sqlseq);
        $responseseq = $insertseq->fetchAll(PDO::FETCH_ASSOC);

        if (!$responseseq){throw new Exception("Não foi possível criar a sequence do usuario");}

        // 3. Criptografa a senha
        $metodo_criptografia = 'aes-256-cbc';
        $chave = 'sua_chave_secreta_de_32_bytes'; // Use a mesma chave sempre!
        $iv = '1234567891234567';
        $criptografia = openssl_encrypt($vl_senha, $metodo_criptografia, $chave, 0, $iv);
        
        // 4. Insere no banco (Incluindo a foto!)
        $sqluser = "INSERT INTO tb_usuario_dn (id_usuario, nu_cpf, nm_usuario, fl_anfitriao, vl_email, nm_sobrenome, vl_senha, vl_foto) 
        VALUES (:id_usuario, :nu_cpf, :nm_usuario, 'false', :vl_email, :nm_sobrenome, :vl_senha, :vl_foto)";
        
        $insertuser = $this->conexao->prepare($sqluser);
        
        $insertuser->bindValue(':id_usuario', $maiorid, PDO::PARAM_INT);
        $insertuser->bindValue(':nu_cpf', $nu_cpf, PDO::PARAM_STR);
        $insertuser->bindValue(':nm_usuario', $nm_usuario, PDO::PARAM_STR);
        $insertuser->bindValue(':vl_email', $vl_email, PDO::PARAM_STR);
        $insertuser->bindValue(':nm_sobrenome', $nm_sobrenome, PDO::PARAM_STR);
        $insertuser->bindValue(':vl_senha', $criptografia, PDO::PARAM_STR);
        $insertuser->bindValue(':vl_foto', $vl_foto, PDO::PARAM_STR); // Salva o link do Firebase
        
        $insertuser->execute();

        // 5. Retorna os dados do usuário criado
        $sqlUsuarioCriado = "select * from tb_usuario_dn where id_usuario = " . $maiorid;
        $getUsuario = $this->conexao->query($sqlUsuarioCriado);
        $responseUsuarioCriado = $getUsuario->fetchAll(PDO::FETCH_ASSOC);

        if (!$responseUsuarioCriado){
            throw new Exception("Não foi possível criar o usuario");
        }
        
        $this->banco->setDados(1, $responseUsuarioCriado);
    }

    // Deleta um usuário
    public function deleteUsuario($dados) {
        $sqluser ="DELETE FROM tb_usuario_dn WHERE id_usuario = ".$dados["id_usuario"];
        $deleteuser = $this->conexao->query($sqluser);
        $responseuser = $deleteuser->fetchAll(PDO::FETCH_ASSOC);
        if (!$responseuser){throw new Exception("Não foi possível deletar usuário");}

        $this->banco->setMensagem(1, "Deletado com sucesso");
        return $responseuser;
    }

    // Realiza o Login
    public function loginUsuario($loginData) {
        if (!isset($loginData['vl_email']) || !isset($loginData['vl_senha'])) {
            throw new Exception("Email ou senha não fornecidos.");
        }

        $metodo_criptografia = 'aes-256-cbc';
        $chave = 'sua_chave_secreta_de_32_bytes';
        $iv = '1234567891234567';
        $vl_senha = openssl_encrypt($loginData['vl_senha'], $metodo_criptografia, $chave, 0, $iv);
        $vl_email = $loginData['vl_email'];

        // IMPORTANTE: Adicionei vl_foto aqui para o app receber a imagem no login
        $sql = "SELECT id_usuario, nm_usuario, nm_sobrenome, vl_email, vl_senha, vl_foto FROM tb_usuario_dn WHERE vl_email = :email";
        
        $consulta = $this->conexao->prepare($sql);
        $consulta->bindValue(':email', $vl_email, PDO::PARAM_STR);
        $consulta->execute();

        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        // Verifica se usuário existe antes de testar a senha
        if ($usuario && isset($usuario['vl_senha']) && $vl_senha == $usuario['vl_senha']) {
            $this->banco->setDados(1, $usuario);
            return("Login bem-sucedido.");
        } else {
            throw new Exception("Email ou senha inválidos.");
        } 
    }

    // Atualiza apenas a foto de perfil (usado na TelaPerfil)
    public function atualizarFotoPerfil($id_usuario, $vl_foto) {
        $sql = "UPDATE tb_usuario_dn SET vl_foto = :vl_foto WHERE id_usuario = :id_usuario";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':vl_foto', $vl_foto, PDO::PARAM_STR);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        
        $this->banco->setDados(1, ["Mensagem" => "Foto atualizada com sucesso"]);
    }
}
?>