<?php
    require_once('./AvaliacaoService.php');
    require_once('../../database/Banco.php');

    try {  
        // Aceita tanto JSON quanto x-www-form-urlencoded
        $jsonPostData = json_decode(file_get_contents("php://input"), true);
        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado";
        
        $banco = new Banco(null,null,null,null,null,null);
        $AvaliacaoService = new AvaliacaoService($banco);
        
        switch ($operacao) {
            
            // Retorna os tipos (1=Comida, 2=Hospitalidade, 3=Pontualidade)
            case 'getTiposAvaliacao':
                $AvaliacaoService->getTiposAvaliacao();
                break;  

            // Salva uma nota vinda do App
            case 'createAvaliacao':
                $id_usuario = $_POST['id_usuario'] ?? throw new Exception("Faltou id_usuario");
                $id_encontro = $_POST['id_encontro'] ?? throw new Exception("Faltou id_encontro");
                $id_avaliacao = $_POST['id_avaliacao'] ?? throw new Exception("Faltou id_avaliacao (tipo)");
                $vl_avaliacao = $_POST['vl_avaliacao'] ?? throw new Exception("Faltou nota");
                
                $AvaliacaoService->createAvaliacao($id_usuario, $id_encontro, $vl_avaliacao, $id_avaliacao);
                break;    

            // Busca a média para exibir no perfil
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
            // Fallback de erro JSON manual
            echo json_encode(["Mensagem" => $e->getMessage()]);
        }
    }      
?>