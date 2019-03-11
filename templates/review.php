<?php
$this->data['header'] = 'Review 2-Step Verification';
$this->includeAtTemplateBase('includes/header.php');

$learnMoreUrl = $this->data['learnMoreUrl'];

?>
<p>
    Please take a moment to review your 2-Factor Authentication Options and
    Password Recovery Methods.
</p>
<p>
    We highly encourage you to do this for your own safety.
</p>
<h3>2-Factor Authentication</h3>
<table>
    <tr>
        <th>Label</th>
        <th>Type</th>
        <th>Created</th>
        <th>Last Used</th>
    </tr>
    <?php foreach ($this->data['mfaOptions'] as $option): ?>
        <tr>
            <td><?= htmlentities($option['label']) ?></td>
            <td><?= htmlentities($option['type']) ?></td>
            <td><?= htmlentities($option['created_utc']) ?></td>
            <td><?= htmlentities($option['last_used_utc']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<h3>Password Recovery Methods</h3>
<table>
    <tr>
        <th>Email</th>
        <th>Verified</th>
        <th>Created</th>
    </tr>
    <?php foreach ($this->data['methodOptions'] as $option): ?>
        <tr>
            <td><?= htmlentities($option['value']) ?></td>
            <td><?= htmlentities($option['verified']) ? 'yes' : 'no' ?></td>
            <td><?= htmlentities($option['created']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<form method="post">
    <button name="updateProfile" style="padding: 4px 8px;">
        Update Profile
    </button>
    
    <button name="continue" style="padding: 4px 8px;">
        Remind me later
    </button>

    <?php if (! empty($learnMoreUrl)): ?>
        <p><a href="<?= htmlentities($learnMoreUrl) ?>"
              target="_blank">Learn more</a></p>
    <?php endif; ?>
</form>
<?php
$this->includeAtTemplateBase('includes/footer.php');
