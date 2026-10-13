<?php

if (empty($_SESSION['userId'])) {
    header('Location: index.php?page=loginRegister');
    exit();
}

$userId = (int)$_SESSION['userId'];
$groupId = isset($_GET['groupId']) ? (int)$_GET['groupId'] : null;
$currentGroup = null;
$isCurrentUserAdmin = false;

if ($groupId) {
    $sqlCurrentGroup = "
        SELECT g.groupsId, g.name, g.createdAt, g.image, g.totalAmount, g.isDeleted
        FROM `groups` g
        WHERE g.groupsId = :groupId
        AND EXISTS (
            SELECT 1 FROM `group_members` gm
            WHERE gm.groupId = g.groupsId AND gm.userId = :userId
        )
    ";
    $stmtCurrent = $pdo->prepare($sqlCurrentGroup);
    $stmtCurrent->bindValue(':groupId', $groupId, PDO::PARAM_INT);
    $stmtCurrent->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmtCurrent->execute();
    $currentGroup = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    if ($currentGroup) {
        $sqlCheckAdmin = "
            SELECT COUNT(*) as is_admin
            FROM `group_admins`
            WHERE groupsId = :groupId AND adminsId = :userId
        ";
        $stmtCheckAdmin = $pdo->prepare($sqlCheckAdmin);
        $stmtCheckAdmin->bindValue(':groupId', $groupId, PDO::PARAM_INT);
        $stmtCheckAdmin->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmtCheckAdmin->execute();
        $adminCheck = $stmtCheckAdmin->fetch(PDO::FETCH_ASSOC);
        $isCurrentUserAdmin = ($adminCheck['is_admin'] > 0);
    }
}

$members = [];
$transactions = [];
$adminUserIds = [];

if ($currentGroup) {
    try {
        // AANGEPAST: createdByUserId weggehaald uit de query en ORDER BY
        $sqlMembers = "
            SELECT gm.groupMemberId, gm.isDeleted, u.userId, u.name
            FROM `group_members` gm
            INNER JOIN `users` u ON gm.userId = u.userId
            WHERE gm.groupId = :groupId AND gm.isDeleted = 0
            ORDER BY u.name
        ";
        $stmtMembers = $pdo->prepare($sqlMembers);
        $stmtMembers->bindValue(':groupId', $groupId, PDO::PARAM_INT);
        $stmtMembers->execute();
        $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

        // Admins ophalen
        $sqladmins = "
            SELECT adminsId
            FROM `group_admins`
            WHERE groupsId = :groupId
        ";
        $stmtAdmins = $pdo->prepare($sqladmins);
        $stmtAdmins->bindValue(':groupId', $groupId, PDO::PARAM_INT);
        $stmtAdmins->execute();
        $adminRows = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
        $adminUserIds = array_column($adminRows, 'adminsId');

        // AANGEPAST: Transacties ophalen - zonder payedByUserId
        $sqlTransactions = "
            SELECT p.paymentId, p.groupsId, p.description, p.date, p.image, p.amount, p.currencyId, p.isDeleted
            FROM `payment` p
            WHERE p.groupsId = :groupId AND p.isDeleted = 0
            ORDER BY p.date DESC
        ";
        $stmtTrans = $pdo->prepare($sqlTransactions);
        $stmtTrans->bindValue(':groupId', $groupId, PDO::PARAM_INT);
        $stmtTrans->execute();
        $transactions = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        echo "Databasefout: " . htmlspecialchars($e->getMessage());
    }
}

$currentGroupMemberId = null;
foreach ($members as $member) {
    if ($member['userId'] == $userId) {
        $currentGroupMemberId = $member['groupMemberId'];
        break;
    }
}

if (!empty($transactions)) {
    $paymentId = $transactions[0]['paymentId'];

    $sqlDebts = "
        SELECT d.paymentId, d.userId, d.amount, d.status, u.name AS debtorName, u.userId
        FROM debts d
        INNER JOIN users u ON d.userId = u.userId
        INNER JOIN payment p ON d.paymentId = p.paymentId
        WHERE d.status = 0 AND d.paymentId = :paymentId AND p.groupsId = :groupId AND p.isDeleted = 0
    ";
    $stmtDebts = $pdo->prepare($sqlDebts);
    $stmtDebts->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
    $stmtDebts->bindValue(':groupId', $groupId, PDO::PARAM_INT);
    $stmtDebts->execute();
    $userdebts = $stmtDebts->fetchAll(PDO::FETCH_ASSOC);
} else {
    $userdebts = [];
}

$sqlDebts = "
    SELECT d.paymentId, d.userId, d.amount, d.status, u.name AS debtorName
    FROM debts d
    INNER JOIN users u ON d.userId = u.userId
    INNER JOIN payment p ON d.paymentId = p.paymentId
    WHERE d.userId = :userId AND d.status = 1 AND p.isDeleted = 0
    LIMIT 1
";
$stmtDebts = $pdo->prepare($sqlDebts);
$stmtDebts->bindValue(':userId', $currentGroupMemberId, PDO::PARAM_INT);
$stmtDebts->execute();
$debt = $stmtDebts->fetch(PDO::FETCH_ASSOC);

foreach (['error', 'success'] as $type) {
    if (!empty($_SESSION[$type])) {
        echo '<div class="' . htmlspecialchars($type) . '-message">' . htmlspecialchars($_SESSION[$type]) . '</div>';
        unset($_SESSION[$type]);
    }
}
$sqlvalutas = "select * from currencies";
$stmtValutas = $pdo->prepare($sqlvalutas);
$stmtValutas->execute();
$valutas = $stmtValutas->fetchAll(PDO::FETCH_ASSOC);

$currencyLookup = [];
foreach ($valutas as $valuta) {
    $currencyLookup[$valuta['currencyId']] = $valuta;
}

function getCurrencySymbol($currencyName) {
    $symbols = [
        'Euro' => '€',
        'dollar' => '$',
        'nakfa' => 'Nfk',
        'US Dollar' => '$',
        'Britse Pond' => '£',
        'Zwitserse Frank' => 'CHF',
        'Japanse Yen' => '¥',
        'Chinese Yuan' => '¥',
        'Noorse Kroon' => 'kr',
        'Zweedse Kroon' => 'kr',
        'Canadese Dollar' => 'C$',
        'Australische Dollar' => 'A$',
        'Turkse Lira' => '₺',
        'Indiase Roepie' => '₹',
        'Braziliaanse Real' => 'R$',
        'Russische Roebel' => '₽',
        'Zuid-Afrikaanse Rand' => 'R'
    ];

    return $symbols[$currencyName] ?? $currencyName;
}

?>

<main class="main-content">
    <?php if ($currentGroup): ?>
        <header class="group-header">
            <div id="groupTitleBtn" aria-pressed="false" aria-controls="sideMember" class="group-title-box" style="cursor:pointer;">
                <img class="group-photo"
                     src="<?= htmlspecialchars($currentGroup['image'] ? 'uploads/' . $currentGroup['image'] : 'img/mybuddy.png') ?>"
                     onerror="this.onerror=null;this.src='img/default.png';"/>
                <span class="group-title"><?= htmlspecialchars($currentGroup['name']) ?></span>
                <?php
                // Voor de groep totaal, pak de valuta van de eerste transactie of default Euro
                $groupCurrency = !empty($transactions) && isset($currencyLookup[$transactions[0]['currencyId']])
                    ? $currencyLookup[$transactions[0]['currencyId']]
                    : ['currencyName' => 'Euro', 'currencySymbol' => '€'];

                $groupCurrencySymbol = !empty($groupCurrency['currencySymbol'])
                    ? $groupCurrency['currencySymbol']
                    : getCurrencySymbol($groupCurrency['currencyName']);
                ?>
                <span class="group-title"><?= htmlspecialchars($groupCurrencySymbol) ?> <?= number_format($currentGroup['totalAmount'] ?? 0, 2, ',', '.') ?></span>
            </div>

            <?php if ($isCurrentUserAdmin): ?>
                <div class="group-actions">
                    <button class="group-create-btn"
                            onclick="location.href='index.php?page=editPayment&groupId=<?= htmlspecialchars($currentGroup['groupsId']) ?>&paymentId=<?= htmlspecialchars($transactions[0]['paymentId'] ?? '') ?>'">
                        EDIT
                    </button>

                    <form enctype="multipart/form-data" action="php/deletePayment.php" method="post">
                        <input type="hidden" name="groupId" value="<?=($currentGroup['groupsId']); ?>">
                        <input type="hidden" name="paymentId" value="<?=($transactions[0]['paymentId'] ?? ''); ?>">
                        <button onclick="confirm('weet je zeker dat je het wilt verwijderen')" class="group-create-btn" type="submit">Delete</button>
                    </form>

                    <button class="group-create-btn"
                            onclick="window.location.href='index.php?page=addPayment&groupId=<?= htmlspecialchars($currentGroup['groupsId']) ?>'">
                        TOEVOEGEN
                    </button>

                    <a href="index.php?page=addUsersToGroup&groupId=<?= htmlspecialchars($currentGroup['groupsId']) ?>">
                        <i class='bx bx-plus-circle action-icon'></i>
                    </a>

                    <button onclick="location.href='index.php?page=invitePage&groupId=<?= htmlspecialchars($currentGroup['groupsId']) ?>'" class="group-create-btn">UITNODIGING</button>
                </div>
            <?php else: ?>
                <div class="group-actions">
                    <button class="group-create-btn"
                            onclick="if(confirm('Weet je zeker dat je deze groep wilt verlaten?'))location.href='php/leaveGroup.php?groupId=<?= htmlspecialchars($groupId) ?>'">
                        LEAVE
                    </button>
                </div>
            <?php endif; ?>
        </header>

        <section class="side-member" id="sideMember" aria-hidden="true" hidden>
            <h3>Leden</h3>
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $member): ?>
                    <div class="groupmember-list">
                        <h5 class="member-name"
                            <?php if ($isCurrentUserAdmin): ?>
                                onclick="openMemberModal('<?= htmlspecialchars($member['name']) ?>', <?= $member['userId'] ?>)"
                                style="cursor: pointer;"
                            <?php endif; ?>>
                            <?= htmlspecialchars($member['name']) ?>
                            <?php if (in_array($member['userId'], $adminUserIds)): ?>
                                <span class="group-creter">GROEPSBEHEERDER</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Geen leden gevonden.</p>
            <?php endif; ?>
        </section>

        <?php if ($isCurrentUserAdmin): ?>
            <div id="id01" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('id01').style.display='none'">&times;</span>
                    <h3 id="modalMemberName">Lid naam</h3>
                    <p>Wat wil je doen met dit lid?</p>
                    <div class="user-edit-delet">
                        <button class="user-btn edit" id="makeAdminBtn"
                                onclick="if(confirm('Weet je zeker dat je ' + currentUserName + ' tot groepsbeheerder wilt maken?')) { window.location.href = 'php/makeAdmin.php?groupId=<?= htmlspecialchars($groupId) ?>&userId=' + currentUserId; }">
                            maak groepsbeheerder
                        </button>
                        <button class="user-btn edit" id="removeAdminBtn" style="display: none;"
                                onclick="if(confirm('Weet je zeker dat je ' + currentUserName + ' als groepsbeheerder wilt verwijderen?')) { window.location.href = 'php/removeAdmin.php?groupId=<?= htmlspecialchars($groupId) ?>&userId=' + currentUserId; }">
                            verwijder als groepsbeheerder
                        </button>
                        <button class="user-btn delete" id="removeMemberBtn"
                                onclick="if(confirm('Weet je zeker dat je ' + currentUserName + ' uit de groep wilt verwijderen?')) { window.location.href = 'php/removeMember.php?groupId=<?= htmlspecialchars($groupId) ?>&userId=' + currentUserId; }">
                            Verwijderen
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $paidAmount = 0;
        if ($currentGroup && $groupId) {
            try {
                $sql = "SELECT SUM(d.amount) AS total_paid
                        FROM debts d
                        INNER JOIN `users` u ON d.userId = u.userId
                        INNER JOIN payment p ON d.paymentId = p.paymentId
                        WHERE p.groupsId = :groupId AND d.status = 1 AND p.isDeleted = 0";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':groupId', $groupId, PDO::PARAM_INT);
                $stmt->execute();
                $paidAmount = (float)$stmt->fetchColumn();

                $totalAmount = (float)($currentGroup['totalAmount'] ?? 0);
                $progressvaluepay = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;

            } catch (PDOException $e) {
                echo "Fout bij ophalen van betaalde bedragen: " . htmlspecialchars($e->getMessage());
                $progressvaluepay = 0;
            }
        } else {
            $progressvaluepay = 0;
        }

        // Percentage gebruikers zonder schuld - alleen voor zichtbare transacties
        $allUserIds = array_column($members, 'userId');
        $debtUserIds = array_column($userdebts, 'userId');

        $noDebtUserIds = array_diff($allUserIds, $debtUserIds);

        $percentage = count($allUserIds) > 0 ? (count($noDebtUserIds) / count($allUserIds)) * 100 : 0;
        ?>

        <div class="progresspay">
            <div class="progress-valuepay" style="width: <?= $progressvaluepay ?>%; transition: width 1s ease;"></div>
            <i class='bx bxs-wallet'></i>
        </div>

        <div class="progressuser">
            <div class="progress-valueuser" style="width: <?= $percentage ?>%; transition: width 1s ease;"></div>
            <i class='bx bxs-user'></i>
        </div>

        <section class="group-list">
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $trans): ?>
                    <?php
                    $currency = isset($currencyLookup[$trans['currencyId']])
                        ? $currencyLookup[$trans['currencyId']]
                        : ['currencyName' => 'Euro', 'currencySymbol' => '€'];

                    $currencySymbol = !empty($currency['currencySymbol'])
                        ? $currency['currencySymbol']
                        : getCurrencySymbol($currency['currencyName']);
                    ?>
                    <div class="group-card">
                        <img class="group-image" src="<?= htmlspecialchars($trans['image'] ?: 'img/mybuddy.png') ?>"
                             alt="Userfoto"/>
                        <span class="group-user-name">Betaling</span>
                        <span class="user-amount"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($trans['amount'], 2, ',', '.') ?></span>
                        <p class="description"><?= htmlspecialchars($trans['description']) ?></p>
                        <small class="date">
                            <?= $trans['date'] ? date('d-m-Y', strtotime($trans['date'])) : 'Onbekende datum' ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Geen transacties gevonden.</p>
            <?php endif; ?>

            <h3>Betaalde Schulden</h3>
            <?php
            $sqlPaidDebts = "
                SELECT d.amount, d.status, p.description, p.date, p.image, p.currencyId, d.userId, u.name AS debtorName
                FROM `debts` d
                INNER JOIN `payment` p ON d.paymentId = p.paymentId
                INNER JOIN `groups` g ON p.groupsId = g.groupsId
                INNER JOIN `users` u ON d.userId = u.userId
                WHERE g.groupsId = :groupId AND p.isDeleted = 0
                ORDER BY p.date DESC
            ";
            $stmtPaidDebts = $pdo->prepare($sqlPaidDebts);
            $stmtPaidDebts->bindValue(':groupId', $groupId, PDO::PARAM_INT);
            $stmtPaidDebts->execute();
            $paidDebts = $stmtPaidDebts->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if (!empty($paidDebts)): ?>
                <?php foreach ($paidDebts as $paid): ?>
                    <?php
                    // Haal de juiste valuta op voor deze schuld
                    $debtCurrency = isset($currencyLookup[$paid['currencyId']])
                        ? $currencyLookup[$paid['currencyId']]
                        : ['currencyName' => 'Euro', 'currencySymbol' => '€'];

                    $debtCurrencySymbol = !empty($debtCurrency['currencySymbol'])
                        ? $debtCurrency['currencySymbol']
                        : getCurrencySymbol($debtCurrency['currencyName']);
                    ?>
                    <div class="group-card">
                        <img class="group-image" src="<?= htmlspecialchars($paid['image'] ?: 'img/mybuddy.png') ?>"
                             alt="Userfoto"/>
                        <span class="group-user-name"><?= htmlspecialchars($paid['debtorName'] ?: 'Onbekend') ?></span>
                        <span class="user-amount"><?= htmlspecialchars($debtCurrencySymbol) ?> <?= number_format($paid['amount'], 2, ',', '.') ?></span>
                        <p class="description"><?= htmlspecialchars($paid['description']) ?></p>
                        <small class="date"><?= $paid['date'] ? date('d-m-Y', strtotime($paid['date'])) : 'Onbekende datum' ?></small>
                        <small class="date">
                            <?= $paid['status'] == 0 ? 'OPEN' : 'BETAALD' ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Geen betaalde schulden gevonden.</p>
            <?php endif; ?>
        </section>

    <?php else: ?>
        <p>Groep niet gevonden of geen toegang.</p>
    <?php endif; ?>

    <?php
    if ($currentGroupMemberId !== null) {
        $sqlDebts = "
        SELECT d.paymentId, d.userId, d.amount, d.status, u.name AS debtorName
        FROM `debts` d
        INNER JOIN `users` u ON d.userId = u.userId
        WHERE d.userId = :userId AND d.status = 0
    ";
        $stmtDebts = $pdo->prepare($sqlDebts);
        $stmtDebts->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmtDebts->execute();
        $debts = $stmtDebts->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($debts)) { ?>
            <aside class="user-pay">
                <button class="pay-btn"
                        onclick="location.href='index.php?page=payment&groupId=<?= htmlspecialchars($groupId) ?>&groupMemberId=<?= htmlspecialchars($userId) ?>'">
                    Betalen
                </button>
            </aside>
        <?php }
    }
    ?>


</main>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const btn = document.getElementById('groupTitleBtn');
        const sideMember = document.getElementById('sideMember');
        if (btn && sideMember) {
            btn.addEventListener('click', () => {
                const hidden = sideMember.hasAttribute('hidden');
                if (hidden) {
                    sideMember.removeAttribute('hidden');
                    sideMember.setAttribute('aria-hidden', 'false');
                    btn.setAttribute('aria-pressed', 'true');
                } else {
                    sideMember.setAttribute('hidden', '');
                    sideMember.setAttribute('aria-hidden', 'true');
                    btn.setAttribute('aria-pressed', 'false');
                }
            });
        }
    });

    function toggleDetails(paymentId) {
        const details = document.getElementById('details-' + paymentId);
        if (details.style.display === 'none') {
            details.style.display = 'block';
        } else {
            details.style.display = 'none';
        }
    }

    <?php if ($isCurrentUserAdmin): ?>
    let currentUserId = null;
    let currentUserName = null;
    const adminUserIds = <?= json_encode($adminUserIds) ?>;

    function openMemberModal(name, userId) {
        currentUserId = userId;
        currentUserName = name;

        document.getElementById('modalMemberName').textContent = name;
        document.getElementById('id01').style.display = 'block';

        const makeAdminBtn = document.getElementById('makeAdminBtn');
        const removeAdminBtn = document.getElementById('removeAdminBtn');

        const isUserAdmin = adminUserIds.includes(parseInt(userId));

        if (isUserAdmin) {
            makeAdminBtn.style.display = 'none';
            removeAdminBtn.style.display = 'inline-block';
        } else {
            makeAdminBtn.style.display = 'inline-block';
            removeAdminBtn.style.display = 'none';
        }
    }

    function closeModal() {
        document.getElementById('id01').style.display = 'none';
    }

    window.onclick = function (event) {
        const modal = document.getElementById('id01');
        if (event.target === modal) {
            modal.style.display = "none";
        }
    }
    <?php else: ?>
    function openMemberModal(name) {
        document.getElementById('modalMemberName').textContent = name;
        document.getElementById('id01').style.display = 'block';
    }

    window.onclick = function (event) {
        const modal = document.getElementById('id01');
        if (event.target === modal) {
            modal.style.display = "none";
        }
    }
    <?php endif; ?>
</script>