<?php
    require_once('./CardapioService.php');
    require_once('../../database/Banco.php');

    try {  
        // Tratamento para POST via x-www-form-urlencoded
        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado [Erro]";
    
        $banco = new Banco(null,null,null,null,null,null);
        $CardapioService = new CardapioService($banco);
        
        switch ($operacao) {
            case 'getCardapiosDisponiveis':
                $CardapioService->getCardapiosDisponiveis();
                break;
            
            case 'createJantar':
                $dados = [
                    'id_usuario'        => $_POST['id_usuario'] ?? throw new Exception("Faltou id_usuario"),
                    'nm_cardapio'       => $_POST['nm_cardapio'] ?? throw new Exception("Faltou titulo"),
                    'ds_cardapio'       => $_POST['ds_cardapio'] ?? throw new Exception("Faltou descricao"),
                    'preco_refeicao'    => $_POST['preco_refeicao'] ?? throw new Exception("Faltou preco"),
                    'hr_encontro'       => $_POST['hr_encontro'] ?? throw new Exception("Faltou data"),
                    'nu_max_convidados' => $_POST['nu_max_convidados'] ?? throw new Exception("Faltou vagas"),
                    'nu_cep'            => $_POST['nu_cep'] ?? null,
                    'nu_casa'           => $_POST['nu_casa'] ?? null,           
                    'vl_foto'           => $_POST['vl_foto'] ?? null,
                    'id_local'          => $_POST['id_local'] ?? null, 
                ];
                $CardapioService->createJantarCompleto($dados);
                break;
                
            case 'getMeuCardapio':
                // Adicionei caso você precise listar os jantares no perfil depois
                if (!isset($_GET['id_local'])) throw new Exception("id_local não informado");
                $CardapioService->getMeuCardapio();
                break;

            case 'deleteCardapio':
                $id_cardapio = $_POST['id_cardapio'] ?? throw new Exception("id_cardapio faltando");
                $CardapioService->deleteJantar($id_cardapio);
                break;

            case 'updateJantar':
                $dados = [
                    'id_cardapio'       => $_POST['id_cardapio'] ?? throw new Exception("Faltou ID"),
                    'nm_cardapio'       => $_POST['nm_cardapio'],
                    'ds_cardapio'       => $_POST['ds_cardapio'],
                    'preco_refeicao'    => $_POST['preco_refeicao'],
                    'hr_encontro'       => $_POST['hr_encontro'],
                    'nu_max_convidados' => $_POST['nu_max_convidados'],
                    'nu_cep'            => $_POST['nu_cep'],
                    'nu_casa'           => $_POST['nu_casa'],
                    'vl_foto'           => $_POST['vl_foto'],
                ];
                $CardapioService->updateJantar($dados);
                break;

            default:
                $banco->setMensagem(1, 'Operação informada não tratada: ' . $operacao);
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