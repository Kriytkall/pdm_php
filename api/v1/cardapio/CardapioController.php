<?php
    require_once('./CardapioService.php');
    require_once('../../database/Banco.php');

    try {  
        // Tratamento para POST via x-www-form-urlencoded (Flutter HttpService novo)
        $operacao = isset($_REQUEST['operacao']) ? $_REQUEST['operacao'] : "Não informado [Erro]";
    
        $banco = new Banco(null,null,null,null,null,null);
        $CardapioService = new CardapioService($banco);
        
        switch ($operacao) {
            case 'getCardapiosDisponiveis':
                $CardapioService->getCardapiosDisponiveis();
                break;
            
            // --- NOVA OPERAÇÃO ---
            case 'createJantar':
                // Coleta todos os dados necessários
                $dados = [
                    'id_usuario' => $_POST['id_usuario'] ?? throw new Exception("Faltou id_usuario"),
                    'nm_cardapio' => $_POST['nm_cardapio'] ?? throw new Exception("Faltou titulo"),
                    'ds_cardapio' => $_POST['ds_cardapio'] ?? throw new Exception("Faltou descricao"),
                    'preco_refeicao' => $_POST['preco_refeicao'] ?? throw new Exception("Faltou preco"),
                    'hr_encontro' => $_POST['hr_encontro'] ?? throw new Exception("Faltou data"),
                    'nu_max_convidados' => $_POST['nu_max_convidados'] ?? throw new Exception("Faltou vagas"),
                    'nu_cep' => $_POST['nu_cep'] ?? throw new Exception("Faltou cep"),
                    'nu_casa' => $_POST['nu_casa'] ?? throw new Exception("Faltou numero"),
                ];
                $CardapioService->createJantarCompleto($dados);
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