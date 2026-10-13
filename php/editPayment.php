<?php
session_start();
include __DIR__ . '/../config/connection.php';

try {
    $userId = $_SESSION['userId'];
    $groupId = (int)$_POST['groupId'];
    $paymentId = (int)$_POST['paymentId'];
    $totalAmount = (float)$_POST['amount'];
    $paidByUserId = (int)$_POST['paidByUserId'];
    $paymentDate = $_POST['paymentDate'];
    $description = trim($_POST['description'] ?? '');
    $distributionMethod = $_POST['distributionMethod'] ?? '';
    $currencyId = (int)$_POST['currencyId'];
    $userShares = array_map('floatval', $_POST['userShares'] ?? []);
    $imageData = $_POST['image'] ?? null;



    if (isset($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
        $updateImage = base64_encode(file_get_contents($_FILES['foto']['tmp_name']));
    } else {
        $updateImage = $imageData;
    }

    if ($groupId <= 0 || $paymentId <= 0) {
        throw new Exception("Ongeldige gegevens.");
    }

    if (!is_array($userShares) || empty($userShares)) {

        $_SESSION['error'] = "Geen geldige verdeling ontvangen.";
        header('Location: ../index.php?page=editPayment&groupId=' . $groupId. '&paymentId=' . $paymentId);
        exit;
    }

    $sqlCheckConfirmed = "
        SELECT 1
        FROM debts d
        INNER JOIN `users` u ON d.userId = u.userId
        WHERE d.paymentId = :paymentId AND gm.groupId = :groupId AND d.status = 1
    ";
    $stmtCheckConfirmed = $pdo->prepare($sqlCheckConfirmed);
    $stmtCheckConfirmed->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
    $stmtCheckConfirmed->bindValue(':groupId', $groupId, PDO::PARAM_INT);
    $confirmedRow = $stmtCheckConfirmed->fetch(PDO::FETCH_ASSOC);

    if ($confirmedRow && $confirmedRow['groupMemberId'] !== $paidByUserId) {
        $_SESSION['error'] = "Betaling is al bevestigd en kan niet worden bewerkt.";
        header('Location: ../index.php?page=editPayment&groupId=' . $groupId. '&paymentId=' . $paymentId);
        exit;
    }

    $sqlCurrency = "SELECT 1 FROM currencies WHERE currencyId = :currencyId";
    $stmtCurrency = $pdo->prepare($sqlCurrency);
    $stmtCurrency->bindValue(':currencyId', $currencyId, PDO::PARAM_INT);
    $stmtCurrency->execute();

    if (!$stmtCurrency->fetch()) {
        $_SESSION['error'] = "Ongeldige valuta.";
        header('Location: ../index.php?page=editPayment&groupId=' . $groupId. '&paymentId=' . $paymentId);
        exit;
    }

    $totalShares = array_sum($userShares);

    if ($distributionMethod === 'Exact bedrag' && abs($totalShares - $totalAmount) > 0.01) {
        $_SESSION['error'] = "Het totaal van de verdeling ($totalShares) komt niet overeen met het opgegeven bedrag";
        header('Location: ../index.php?page=editPayment&groupId=' . $groupId. '&paymentId=' . $paymentId);
        exit;
    }

    $sqlUpdatePayment = "
        UPDATE payment
        SET description = :description,
            date = :date,
            amount = :amount,
            currencyId = :currencyId,
            image = :image
        WHERE paymentId = :paymentId
    ";
    $stmtUpdatePayment = $pdo->prepare($sqlUpdatePayment);
    $stmtUpdatePayment->bindValue(':description', $description, PDO::PARAM_STR);
    $stmtUpdatePayment->bindValue(':date', $paymentDate, PDO::PARAM_STR);
    $stmtUpdatePayment->bindValue(':amount', $totalAmount);
    $stmtUpdatePayment->bindValue(':currencyId', $currencyId, PDO::PARAM_INT);
    $stmtUpdatePayment->bindValue(':image', $updateImage);
    $stmtUpdatePayment->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
    $stmtUpdatePayment->execute();

    $updateGroupSql = "
        UPDATE groups 
        SET totalAmount = :totalAmount
        WHERE groupsId = :groupId
    ";
    $updateGroupStmt = $pdo->prepare($updateGroupSql);
    $updateGroupStmt->execute([
        ':totalAmount' => $totalAmount,
        ':groupId' => $groupId
    ]);

    foreach ($userShares as $groupMemberId => $amount) {

        $sqlCheckDebt = "
            SELECT 1 FROM debts
            WHERE paymentId = :paymentId AND userId = :userId
        ";
        $stmtCheckDebt = $pdo->prepare($sqlCheckDebt);
        $stmtCheckDebt->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
        $stmtCheckDebt->bindValue(':userId', (int)$groupMemberId, PDO::PARAM_INT);
        $stmtCheckDebt->execute();

        if ($stmtCheckDebt->fetch()) {
            $sqlUpdateDebt = "
                UPDATE debts
                SET amount = :amount
                WHERE paymentId = :paymentId AND userId = :userId
            ";
            $stmtUpdateDebt = $pdo->prepare($sqlUpdateDebt);
            $stmtUpdateDebt->bindValue(':amount', (float)$amount);
            $stmtUpdateDebt->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
            $stmtUpdateDebt->bindValue(':userId', (int)$groupMemberId, PDO::PARAM_INT);
            $stmtUpdateDebt->execute();
        } else {
            $sqlInsertDebt = "
                INSERT INTO debts (paymentId, userId, amount, status)
                VALUES (:paymentId, :userId, :amount, 0)
            ";
            $stmtInsertDebt = $pdo->prepare($sqlInsertDebt);
            $stmtInsertDebt->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
            $stmtInsertDebt->bindValue(':userId', (int)$groupMemberId, PDO::PARAM_INT);
            $stmtInsertDebt->bindValue(':amount', (float)$amount);
            $stmtInsertDebt->execute();
        }
    }


    $_SESSION['success'] = "Betaling en schulden succesvol bijgewerkt.";
    header("Location: ../index.php?page=groupchat&groupId=" . urlencode($groupId));
    exit();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
