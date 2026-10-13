<?php
include __DIR__ . '/../config/connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    $confirmEmail = strtolower(trim($_POST['confirm_email']));
    $password = $_POST['password'];
    $name = $_POST['name'];
    $registrationDate = date("Y-m-d");

    try {
        if ($email !== $confirmEmail) {
            $_SESSION['error'] = "De emails komen niet overeen.";
            header('Location: ../index.php?page=loginRegister');
            exit;
        }
        $sql = "SELECT email FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Dit e-mailadres is al in gebruik.";
            header('Location: ../index.php?page=loginRegister');
            exit;
        }

        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

        if (!preg_match($pattern, $password)) {
            $_SESSION['error'] = "Wachtwoord moet minimaal 8 tekens bevatten, met minstens één hoofdletter, één kleine letter, één cijfer en één speciaal teken.";
            header('Location: ../index.php?page=loginRegister');
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Fout bij registratie: " . $e->getMessage();
        header('Location: ../index.php?page=loginRegister');
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $sql = 'INSERT INTO users (email, password, name, registrationDate) 
            VALUES (:email, :password, :name, :registrationDate)';
    $statement = $pdo->prepare($sql);
    $statement->execute([
        'email' => $email,
        'password' => $hashedPassword,
        'name' => $name,
        'registrationDate' => $registrationDate,
    ]);
    $newUserId = $pdo->lastInsertId();

    if (isset($_SESSION['invite_token'])) {
        $inviteToken = $_SESSION['invite_token'];

        $stmt = $pdo->prepare("SELECT * FROM invitations WHERE token = :token AND status = 0");
        $stmt->execute(['token' => $inviteToken]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invite) {
            $groupId = $invite['groupId'];

            $link = $pdo->prepare("INSERT INTO group_members (userId, groupId) VALUES (:userId, :groupId)");
            $link->execute([
                'userId' => $newUserId,
                'groupId' => $groupId
            ]);

            $update = $pdo->prepare("UPDATE invitations SET status = 1 WHERE invitationId = :id");
            $update->execute(['id' => $invite['invitationId']]);

            unset($_SESSION['invite_token']);
        }
    }


    $_SESSION['userId'] = $newUserId;
    $_SESSION['email'] = $email;

    header("Location: ../index.php?page=groupsViews");
    exit;


} else {
    echo "Ongeldige aanvraagmethode.";
}
?>
