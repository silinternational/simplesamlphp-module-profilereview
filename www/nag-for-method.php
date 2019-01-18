<?php

use Sil\SspProfileReview\LoggerFactory;
use sspmod_profilereview_Auth_Process_ProfileReview as ProfileReview;

$stateId = filter_input(INPUT_GET, 'StateId') ?? null;
if (empty($stateId)) {
    throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}

$state = SimpleSAML_Auth_State::loadState($stateId, ProfileReview::STAGE_SENT_TO_NAG);
$logger = LoggerFactory::getAccordingToState($state);

// If the user has pressed the set-up-Method button...
if (filter_has_var(INPUT_POST, 'setUpMethod')) {
    ProfileReview::redirectToProfile($state);
    return;
} elseif (filter_has_var(INPUT_POST, 'continue')) {
    // The user has pressed the continue button.
    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
    return;
}

$globalConfig = SimpleSAML_Configuration::getInstance();

$template = $state['nagType'] === 'add'
    ? 'profilereview:nag-for-method.php'
    : 'profilereview:nag-for-method-review.php';

$t = new SimpleSAML_XHTML_Template($globalConfig, $template);
$t->data['learnMoreUrl'] = $state['methodLearnMoreUrl'];
$t->show();

$logger->info(sprintf(
    'profilereview: Encouraged Employee ID %s to %s Method.',
    $state['employeeId'],
    $state['nagType']
));
