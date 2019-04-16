<?php

use Sil\SspProfileReview\LoggerFactory;
use sspmod_profilereview_Auth_Process_ProfileReview as ProfileReview;

$stateId = filter_input(INPUT_GET, 'StateId') ?? null;
if (empty($stateId)) {
    throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}

$state = SimpleSAML_Auth_State::loadState($stateId, ProfileReview::STAGE_SENT_TO_NAG);
$logger = LoggerFactory::getAccordingToState($state);

// If the user has pressed the update button...
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

$t = new SimpleSAML_XHTML_Template($globalConfig, 'profilereview:' . $state['template']);
$t->data['profileUrl'] = $state['profileUrl'];
$t->data['methodOptions'] = $state['methodOptions'];
$t->data['mfaOptions'] = $state['mfaOptions'];
$t->data['mfaLearnMoreUrl'] = $state['mfaLearnMoreUrl'];
$t->show();

$logger->warning(json_encode([
    'module' => 'profilereview',
    'event' => 'presented nag',
    'template' => $state['template'],
    'employeeId' => $state['employeeId'],
]));
