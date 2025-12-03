<?php
require_once('../../database/InstanciaBanco.php');

class AvaliacaoService extends InstanciaBanco {

    public function createAvaliacao($id_usuario, $id_encontro, $vl_avaliacao, $id_avaliacao) {
        
        $check = $this->conexao->prepare("SELECT * FROM tb_avaliacao_encontro_dn WHERE id_usuario = :user AND id_encontro = :enc AND id_avaliacao = :tipo");
        $check->execute([':user' => $id_usuario, ':enc' => $id_encontro, ':tipo' => $id_avaliacao]);
        
        if ($check->rowCount() > 0) {
            throw new Exception("Você já avaliou este critério para este jantar.");
        }

        $sql = "INSERT INTO tb_avaliacao_encontro_dn (id_usuario, id_encontro, vl_avaliacao, id_avaliacao) 
                VALUES (:id_usuario, :id_encontro, :vl_avaliacao, :id_avaliacao)";

        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':id_encontro', $id_encontro, PDO::PARAM_INT);
        $stmt->bindValue(':vl_avaliacao', (int)$vl_avaliacao, PDO::PARAM_INT);
        $stmt->bindValue(':id_avaliacao', $id_avaliacao, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->banco->setDados(1, ["Mensagem" => "Avaliação registrada!"]);
        } else {
            throw new Exception("Erro ao salvar avaliação.");
        }
    }

    public function getTiposAvaliacao() {
        $sql = "SELECT * FROM tb_tipo_avaliacao_dn";
        $consulta = $this->conexao->query($sql);
        $resultados = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->banco->setDados(count($resultados), $resultados);
        if (!$resultados) { $this->banco->setDados(0, []); }
    }

    public function getMediaAvaliacaoUsuario($id_anfitriao) {
        // --- CORREÇÃO NA QUERY ---
        // Usamos COUNT(av.vl_avaliacao) em vez de id_avaliacao_encontro que não existe
        $sql = "
            SELECT 
                COALESCE(AVG(av.vl_avaliacao), 0) as media_geral, 
                COUNT(av.vl_avaliacao) as total_avaliacoes 
            FROM tb_usuario_dn u
            LEFT JOIN tb_local_dn l ON u.id_usuario = l.id_usuario
            LEFT JOIN tb_encontro_dn e ON l.id_local = e.id_local
            LEFT JOIN tb_avaliacao_encontro_dn av ON e.id_encontro = av.id_encontro
            WHERE u.id_usuario = :id_anfitriao
        ";

        $stmt = $this->conexao->prepare($sql);
        $stmt->bindValue(':id_anfitriao', $id_anfitriao, PDO::PARAM_INT);
        $stmt->execute();
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $media = $resultado['media_geral'] ? round($resultado['media_geral'], 1) : 0;
        $total = $resultado['total_avaliacoes'] ? $resultado['total_avaliacoes'] : 0;

        // Como salvamos 3 notas por jantar (Comida, Hospitalidade, Pontualidade),
        // o total real de "avaliações de pessoas" é o total de registros dividido por 3.
        // Mas para simplificar e mostrar atividade, vamos mostrar o total de itens avaliados.
        
        $this->banco->setDados(1, [
            "media" => $media,
            "total" => $total
        ]);
    }
}
?>