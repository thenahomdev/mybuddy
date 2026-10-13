<?php
$today = date("Y-m-d");

$userId = (int)($_SESSION['userId'] ?? 0);
$groupId = (int)($_GET['groupId'] ?? 0);
$paymentId = (int)($_GET['paymentId'] ?? 0);

$sqlMembers = "
    SELECT gm.groupMemberId, u.userId, u.name
    FROM group_members gm
    INNER JOIN users u ON gm.userId = u.userId
    INNER JOIN `groups` g ON gm.groupId = g.groupsId
    WHERE gm.groupId = :groupId AND gm.isDeleted = 0
";
$stmtMembers = $pdo->prepare($sqlMembers);
$stmtMembers->bindValue(':groupId', $groupId, PDO::PARAM_INT);
$stmtMembers->execute();
$members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

$groupCreatorId = null;
$groupCreatorName = null;

// Get admin info first before using it
$sqlAdmin = "
    SELECT ga.adminsId, u.name
    FROM group_admins ga
    INNER JOIN `users` u ON ga.adminsId = u.userId
    WHERE groupsId = :groupId
";
$stmtAdmin = $pdo->prepare($sqlAdmin);
$stmtAdmin->bindValue(':groupId', $groupId, PDO::PARAM_INT);
$stmtAdmin->execute();
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

$adminUserId = $admin['adminsId'] ?? null;
$adminName = $admin['name'] ?? null;

// Now fix the group creator logic
foreach ($members as $member) {
    if ($member['userId'] == $adminUserId) {
        $groupCreatorId = $member['groupMemberId'];
        $groupCreatorName = $member['name'];
        break;
    }
}

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

// Get the specific transaction for editing
$transaction = null;
foreach ($transactions as $trans) {
    if ($trans['paymentId'] == $paymentId) {
        $transaction = $trans;
        break;
    }
}

$sqlShares = "
    SELECT * FROM debts WHERE paymentId = :paymentId
";
$stmtShares = $pdo->prepare($sqlShares);
$stmtShares->bindValue(':paymentId', $paymentId, PDO::PARAM_INT);
$stmtShares->execute();
$shares = $stmtShares->fetchAll(PDO::FETCH_ASSOC);

$userShares = [];
foreach ($shares as $share) {
    // Debug: Check what columns exist in the debts table
    // Uncomment the next line to see available columns:
    // var_dump(array_keys($share));

    // Try different possible column names for group member ID
    $memberKey = null;
    if (isset($share['groupMemberId'])) {
        $memberKey = $share['groupMemberId'];
    } elseif (isset($share['group_member_id'])) {
        $memberKey = $share['group_member_id'];
    } elseif (isset($share['memberId'])) {
        $memberKey = $share['memberId'];
    } elseif (isset($share['member_id'])) {
        $memberKey = $share['member_id'];
    } elseif (isset($share['userId'])) {
        $memberKey = $share['userId'];
    } elseif (isset($share['user_id'])) {
        $memberKey = $share['user_id'];
    }

    if ($memberKey !== null && isset($share['amount'])) {
        $userShares[$memberKey] = $share['amount'];
    }
}
?>

<div class="form-container">

    <?php foreach (['error', 'success'] as $type): ?>
        <?php if (!empty($_SESSION[$type])): ?>
            <div class="<?= $type ?>-message">
                <?= htmlspecialchars($_SESSION[$type]); unset($_SESSION[$type]); ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <form action="php/editPayment.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="groupId" value="<?= htmlspecialchars($groupId) ?>">
        <input type="hidden" name="paymentId" value="<?= htmlspecialchars($paymentId) ?>">

        <div class="form-grid">
            <div>
                <label for="amount">Bedrag</label>
                <div class="inline-select">
                    <input
                            type="number"
                            id="amount"
                            name="amount"
                            placeholder="0,00"
                            step="0.01"
                            min="0"
                            required
                            value="<?= htmlspecialchars($transaction['amount'] ?? '') ?>"
                    >
                    <span>EUR</span>
                </div>
            </div>

            <div>
                <label for="paidByUserId">Betaald door</label>
                <?php if ($adminUserId !== null): ?>
                    <select name="paidByUserId" id="paidByUserId" required>
                        <option value="<?= htmlspecialchars($adminUserId) ?>">
                            <?= htmlspecialchars($adminName) ?>
                            <?php if ($userId == $adminUserId): ?> (Jij)<?php endif; ?>
                        </option>
                    </select>

                <?php else: ?>
                    <p>Geen groepsmaker gevonden.</p>
                <?php endif; ?>

                <label for="paymentDate" style="margin-top: 10px;">Betaaldatum</label>
                <input
                        type="date"
                        id="paymentDate"
                        name="paymentDate"
                        max="<?= $today ?>"
                        required
                        value="<?= htmlspecialchars($transaction['date'] ?? $today) ?>"
                >
            </div>
        </div>

        <div>
            <label for="currencyId">Valuta</label>
            <select name="currencyId" id="currencyId" required>
                <?php
                $stmt = $pdo->query("SELECT * FROM currencies");
                while ($currency = $stmt->fetch()) {
                    $sel = ($currency['currencyId'] == ($transaction['currencyId'] ?? null)) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($currency['currencyId']) . '" ' . $sel . '>' . htmlspecialchars($currency['currencyName']) . '</option>';
                }
                ?>
            </select>
        </div>

        <div class="form-grid" style="margin-top: 10px;">
            <div>
                <label for="description">Omschrijving</label>
                <input
                        type="text"
                        id="description"
                        name="description"
                        placeholder="bijv. borreltje"
                        required
                        value="<?= htmlspecialchars($transaction['description'] ?? '') ?>"
                >
            </div>
            <div>
                <label>Huidige foto</label><br>
                <?php if (!empty($transaction['image'])): ?>
                    <img class="Huidige-photo"
                         src="data:image/jpeg;base64,<?= $transaction['image'] ?>"
                         onerror="this.onerror=null;this.src='img/default.png';"
                         style="max-height:100px;"
                         alt="Huidige foto"
                         required
                    /><br>
                <?php endif; ?>

                <label class="upload-box" for="fotoUpload" style="cursor:pointer;">📷 Klik om een foto te uploaden</label>
                <input type="file" id="fotoUpload" name="foto" accept="image/*" style="display:none;">
            </div>
        </div>

        <div style="margin-top: 10px;">
            <label for="distributionMethod">Verdeel</label>
            <div class="inline-select" style="display:flex; align-items:center; gap:10px;">
                <select name="distributionMethod" id="distributionMethod" required>
                    <option value="shares">Aandelen</option>
                    <option value="exact">Exact bedrag</option>
                </select>
                <button type="button" id="everyoneOneShareBtn" style="display:none;">👥 Iedereen 1x</button>
            </div>
        </div>

        <div class="users-section" style="margin-top: 10px;">
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $member):
                    $amount = $userShares[$member['userId']] ?? '';
                    ?>
                    <div class="user-row" style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                        <span class="label" style="flex:1;">
                            <?= htmlspecialchars($member['name']) ?>
                            <?php if ($member['userId'] === $userId): ?>
                                <small>Jij</small>
                            <?php endif; ?>
                        </span>
                        <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="userShares[<?= htmlspecialchars($member['userId']) ?>]"
                                value="<?= htmlspecialchars($amount) ?>"
                                aria-label="Bedrag voor <?= htmlspecialchars($member['name']) ?>"
                                style="width:70px; text-align:right;"
                                class="user-share"
                                readonly
                        >
                        <span class="count" style="width:30px; text-align:center;">0x</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Geen leden om te verdelen.</p>
            <?php endif; ?>
        </div>

        <div class="controls" style="margin-top: 15px;">
            <button type="button" class="save" onclick="location.href='index.php?page=groupchat&groupId=<?= htmlspecialchars($groupId) ?>'">Terug</button>
            <button type="submit" class="submit">Aanpassen</button>
        </div>
    </form>
</div>

<script>
    function updateCountDisplay(input, value) {
        const countSpan = input.parentElement.querySelector('.count');
        if (countSpan) countSpan.textContent = `${value}x`;
    }

    const distributionMethodSelect = document.getElementById('distributionMethod');
    const everyoneBtn = document.getElementById('everyoneOneShareBtn');

    function toggleEveryoneButton() {
        const isShares = distributionMethodSelect.value === 'shares';
        everyoneBtn.style.display = isShares ? 'inline-block' : 'none';

        document.querySelectorAll('.user-share').forEach(input => {
            input.readOnly = isShares;  // readonly ipv disabled
        });
    }

    distributionMethodSelect.addEventListener('change', toggleEveryoneButton);
    toggleEveryoneButton();

    everyoneBtn.addEventListener('click', () => {
        const amountInput = document.getElementById('amount');
        const amount = parseFloat(amountInput.value);
        if (isNaN(amount) || amount <= 0) {
            alert('Vul eerst een geldig bedrag in!');
            return;
        }

        const members = document.querySelectorAll('.user-share');
        const perPerson = parseFloat((amount / members.length).toFixed(2));

        members.forEach(input => {
            input.value = perPerson;
            updateCountDisplay(input, 1);
        });
    });
</script>