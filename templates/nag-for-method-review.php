<?php
$this->data['header'] = 'Review Recovery Methods';
$this->includeAtTemplateBase('includes/header.php');

$learnMoreUrl = $this->data['learnMoreUrl'];
?>
<p>
    It's time to review your account recovery methods for accuracy.
</p>
<p>
    We highly encourage you to do this to ensure continuous access and improved security.
</p>
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
    <button name="setUpMfa" style="padding: 4px 8px;">
        Update Recovery Methods
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
