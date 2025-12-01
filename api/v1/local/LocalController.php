<?php
      
    require_once('./LocalService.php');
    require_once('../../database/Banco.php');

    try {  
        $jsonPostData = json_decode(file_get_contents("php://input"), true);
        
        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado [Erro]";
    
        $banco = new Banco(null,null,null,null,null,null);
        
        $LocalService = new LocalService($banco);
        
        switch ($operacao) {
            case 'getLocal':
                $LocalService->getLocal();
                break;   
            case 'getLocais':
                $LocalService->getLocais();
                break;
            
            case 'getMeusLocais':
                if (!isset($_GET['id_usuario'])) {
                    throw new Exception("id_usuario não informado na URL.");
                }
                $LocalService->getMeusLocais();
                break; 

            case 'createLocal':
                $nu_cep = isset($_POST['nu_cep']) ? $_POST['nu_cep'] : throw new Exception("campo nu_cep não fornecido");
                $nu_casa = isset($_POST['nu_casa']) ? $_POST['nu_casa'] : throw new Exception("campo nu_casa não fornecido");
                $id_usuario = isset($_POST['id_usuario']) ? $_POST['id_usuario'] : throw new Exception("campo id_usuario não fornecido");
                
                // Campos opcionais
                $nu_cnpj = isset($_POST['nu_cnpj']) ? $_POST['nu_cnpj'] : null;
                $dc_complemento = isset($_POST['dc_complemento']) ? $_POST['dc_complemento'] : null;
                
                $LocalService->createLocal($nu_cep, $nu_casa, $id_usuario, $nu_cnpj, $dc_complemento);
                break;    
            
            case 'deleteLocal':
                $id_local = isset($_POST['id_local']) ? $_POST['id_local'] : throw new Exception("campo id_local não fornecido");
                $LocalService->deleteLocal($id_local);
                break; 
            
            default:
                $banco->setMensagem(1, 'Operação informada não tratada. Operação=' . $operacao);
                break;
        }

        echo $banco->getRetorno();
        unset($banco);
    }
    catch(Exception $e) {   
        if (isset($banco)) {   
            $banco->setMensagem(1, $e->getMessage());
            echo $banco->getRetorno();
            unset($banco);
        } else {
            echo json_encode(["Mensagem" => $e->getMessage()]);
        }
    }
?>