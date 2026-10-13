<?php
session_start();
include __DIR__ . '/../config/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paymentId'], $_POST['groupId'])) {
    $paymentId = $_POST['paymentId'];
    $groupId = $_POST['groupId'];

    $sql = "SELECT COUNT(*) FROM debts WHERE paymentId = :paymentId AND status = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 1) {
        $_SESSION['error'] = "Je mag deze betaling niet verwijderen als meerdere mensen al hebben betaald.";
        header('Location: ../index.php?page=groupchat&groupId=' . urlencode($groupId));
        exit;
    }


    try {
        $sql = "UPDATE payment SET isDeleted = 1 WHERE paymentId = :paymentId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
        $stmt->execute();

        $sql = "UPDATE groups SET totalAmount = 0 WHERE groupsId = :groupId ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':groupId', $groupId, PDO::PARAM_INT);
        $stmt->execute();

        $sql = "UPDATE debts SET status = 1 WHERE paymentId = :paymentId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
        $stmt->execute();


        $_SESSION['success'] = "Groep en alle gerelateerde betalingen zijn gedeactiveerd";
        header('Location: ../index.php?page=groupchat&groupId=' . urlencode($groupId));
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Fout bij deactiveren groep: " . $e->getMessage();
        header('Location: ../index.php?page=groupchat&groupId=' . urlencode($groupId));
        exit;
    }
} else {
    $_SESSION['error'] = "Geen groep of betaling opgegeven.";
    header('Location: ../index.php?page=groupchat');
    exit;
}
?>