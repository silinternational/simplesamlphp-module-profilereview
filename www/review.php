<?php

use Sil\SspProfileReview\LoggerFactory;
use sspmod_profilereview_Auth_Process_ProfileReview as ProfileReview;

$stateId = filter_input(INPUT_GET, 'StateId') ?? null;
if (empty($stateId)) {
    throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}

$state = SimpleSAML_Auth_State::loadState($stateId, ProfileReview::STAGE_SENT_TO_NAG);
$logger = LoggerFactory::getAccordingToState($state);

/* Skip the splash page for awhile to avoid annoying them with constant warnings. */
$oneDay = 24 * 60 * 60;
ProfileReview::skipSplashPagesFor($oneDay);

// If the user has pressed the set-up-Method button...
if (filter_has_var(INPUT_POST, 'update')) {
    ProfileReview::redirectToProfile($state);
    return;
} elseif (filter_has_var(INPUT_POST, 'continue')) {
    // The user has pressed the continue button.
    unset($state['Attributes']['mfa']);
    unset($state['Attributes']['method']);
    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
    return;
}

$globalConfig = SimpleSAML_Configuration::getInstance();

$t = new SimpleSAML_XHTML_Template($globalConfig, 'profilereview:review.php');
$t->data['profileUrl'] = $state['profileUrl'];
$t->data['methodOptions'] = $state['methodOptions'];
$t->data['mfaOptions'] = $state['mfaOptions'];
$t->show();

$logger->warning(json_encode([
    'module' => 'profilereview',
    'event' => 'presented profile review',
    'employeeId' => $state['employeeId'],
]));
