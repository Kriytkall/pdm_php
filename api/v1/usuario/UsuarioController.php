<?php
      
    require_once('UsuarioService.php');
    require_once('../../database/Banco.php');

    try {  
        // Tenta pegar JSON se for enviado como raw (opcional, mas bom manter)
        $postdata = json_decode(file_get_contents("php://input"), true);

        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado [Erro]";

        $banco = new Banco(null,null,null,null,null,null);
        
        $usuarioService = new UsuarioService($banco);

        switch ($operacao) {
            case 'getUsuarios':
                $usuarioService->getUsuarios();
                break;   
            case 'getUsuario':
                $usuarioService->getUsuario();
                break;
            case 'createUsuario':
                $nu_cpf = isset($_POST['nu_cpf']) ? $_POST['nu_cpf'] :  throw new Exception("nu_cpf não definido");
                $nm_usuario = isset($_POST['nm_usuario']) ? $_POST['nm_usuario'] :  throw new Exception("nm_usuario não definido");
                $vl_email = isset($_POST['vl_email']) ? $_POST['vl_email'] :  throw new Exception("vl_email não definido");
                $nm_sobrenome = isset($_POST['nm_sobrenome']) ? $_POST['nm_sobrenome'] :  throw new Exception("nm_sobrenome não definido");
                $vl_senha = isset($_POST['vl_senha']) ? $_POST['vl_senha'] : throw new Exception("vl_senha não definido");
                
                // --- CORREÇÃO AQUI ---
                // Pega o vl_foto (pode ser null se não vier)
                $vl_foto = isset($_POST['vl_foto']) ? $_POST['vl_foto'] : null;

                // Passa o $vl_foto para o serviço
                $usuarioService->createUsuario($nu_cpf, $nm_usuario, $vl_email, $nm_sobrenome, $vl_senha, $vl_foto);
                break;
            
            case 'deleteUsuario':
                $usuarioService->deleteUsuario($_POST);
                break;
            case 'updateUsuario':
                $usuarioService->updateUsuario(dados: $_POST);
                break;
            case 'loginUsuario':
                $usuarioService->loginUsuario(loginData: $_POST);
                break;
                
            // Adicionado caso queira usar a atualização de foto isolada
            case 'atualizarFotoPerfil':
                $id_usuario = isset($_POST['id_usuario']) ? $_POST['id_usuario'] : throw new Exception("id_usuario nao fornecido");
                $vl_foto = isset($_POST['vl_foto']) ? $_POST['vl_foto'] : throw new Exception("vl_foto nao fornecido");
                $usuarioService->atualizarFotoPerfil($id_usuario, $vl_foto);
                break;

            default:
                $banco->setMensagem(1,'Operacão informada nao tratada. Operação=' . $operacao );
                break;
            }

        echo $banco->getRetorno();
        unset($banco);
    }
    catch(Exception $e) {   
        if (isset($banco)) {   
            $banco->setMensagem(1,$e->getMessage());
            echo $banco->getRetorno();
            unset($banco);
        }   
        else {
            echo $e.getMessage();
        }
    }
            
?>