<?php
    require_once('./AvaliacaoService.php');
    require_once('../../database/Banco.php');

    try {  
        $jsonPostData = json_decode(file_get_contents("php://input"), true);
        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado";
        
        $banco = new Banco(null,null,null,null,null,null);
        $AvaliacaoService = new AvaliacaoService($banco);
        
        switch ($operacao) {
            
            case 'getTiposAvaliacao':
                $AvaliacaoService->getTiposAvaliacao();
                break;  

            case 'createAvaliacao':
                $id_usuario = $_POST['id_usuario'] ?? throw new Exception("Faltou id_usuario");
                $id_encontro = $_POST['id_encontro'] ?? throw new Exception("Faltou id_encontro");
                $id_avaliacao = $_POST['id_avaliacao'] ?? throw new Exception("Faltou id_avaliacao (tipo)");
                $vl_avaliacao = $_POST['vl_avaliacao'] ?? throw new Exception("Faltou nota");
                
                $AvaliacaoService->createAvaliacao($id_usuario, $id_encontro, $vl_avaliacao, $id_avaliacao);
                break;    

            case 'getMediaUsuario':
                $id_usuario = $_GET['id_usuario'] ?? throw new Exception("Faltou id_usuario");
                $AvaliacaoService->getMediaAvaliacaoUsuario($id_usuario);
                break;

            default:
                $banco->setMensagem(1, 'Operação não tratada: ' . $operacao);
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