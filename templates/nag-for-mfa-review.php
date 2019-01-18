<?php
$this->data['header'] = 'Review 2-Step Verification';
$this->includeAtTemplateBase('includes/header.php');

$learnMoreUrl = $this->data['learnMoreUrl'];
?>
<p>
    It's time to review your 2-Step Verification options.
</p>
<p>
    We highly encourage you to do this for your own safety
</p>
<form method="post">
    <button name="setUpMfa" style="padding: 4px 8px;">
        Review 2-step verification
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
