<?php
    require_once('./EncontroService.php');
    require_once('../../database/Banco.php');
    
    try {  
        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado";
        $banco = new Banco(null,null,null,null,null,null);
        $EncontroService = new EncontroService($banco);
        
        switch ($operacao) {
            case 'reservar':
            case 'addUsuarioEncontro':
                $id_encontro = $_POST['id_encontro'] ?? throw new Exception("id_encontro faltando");
                $id_usuario = $_POST['id_usuario'] ?? throw new Exception("id_usuario faltando");
                $deps = $_POST['nu_dependentes'] ?? 0;
                $EncontroService->addUsuarioEncontro($id_usuario, $id_encontro, $deps);
                break; 
            
            case 'aprovarReserva':
                $id_encontro = $_POST['id_encontro'] ?? throw new Exception("id_encontro faltando");
                $id_convidado = $_POST['id_convidado'] ?? throw new Exception("id_convidado faltando");
                $EncontroService->aprovarReserva($id_encontro, $id_convidado);
                break;

            case 'rejeitarReserva':
                $id_encontro = $_POST['id_encontro'] ?? throw new Exception("id_encontro faltando");
                $id_convidado = $_POST['id_convidado'] ?? throw new Exception("id_convidado faltando");
                $EncontroService->rejeitarReserva($id_encontro, $id_convidado);
                break;

            case 'getParticipantes':
                $id_encontro = $_GET['id_encontro'] ?? throw new Exception("id_encontro faltando");
                $EncontroService->getParticipantes($id_encontro);
                break;

            case 'cancelarReserva':
            case 'deleteUsuarioEncontro':
                $id_encontro = $_POST['id_encontro'] ?? throw new Exception("id_encontro faltando");
                $id_usuario = $_POST['id_usuario'] ?? throw new Exception("id_usuario faltando");
                $EncontroService->deleteUsuarioEncontro($id_usuario, $id_encontro);
                break;

            case 'verificarReserva':
                $id_encontro = $_GET['id_encontro'] ?? throw new Exception("id_encontro faltando");
                $id_usuario = $_GET['id_usuario'] ?? throw new Exception("id_usuario faltando");
                $EncontroService->verificarReserva($id_usuario, $id_encontro);
                break;

            case 'getMinhasReservas':
                $id_usuario = $_GET['id_usuario'] ?? throw new Exception("id_usuario faltando");
                $EncontroService->getMinhasReservas($id_usuario);
                break;   

            case 'getMeusJantaresCriados':
                $id_usuario = $_GET['id_usuario'] ?? throw new Exception("id_usuario faltando");
                $EncontroService->getMeusJantaresCriados($id_usuario);
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