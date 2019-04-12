Feature: Prompt to review profile information

  Scenario: Don't ask for review
    Given I provide credentials that do not need review
    When I login
    Then I should end up at my intended destination

  Scenario: Present reminder as required by the user profile
    Given I provide credentials that are due for a profile review reminder
    When I login
    Then I should see a message encouraging me to review my profile
      And there should be a way to go update my profile now
      And there should be a way to continue to my intended destination

  Scenario Outline: Obeying a reminder
    Given I provide credentials that are due for a <category> <nag type> reminder
      And I have logged in
    When I click the update profile button
    Then I should end up at the update profile URL

    Examples:
      | category | nag type |
      | mfa      | add      |
      | method   | add      |
      | profile  | review   |

  Scenario Outline: Ignoring a reminder
    Given I provide credentials that are due for a <category> <nag type> reminder
      And I have logged in
    When I click the remind-me-later button
    Then I should end up at my intended destination

    Examples:
      | category | nag type |
      | mfa      | add      |
      | method   | add      |
      | profile  | review   |

  Scenario: Ensuring that manager mfa data is not displayed to the user
    Given I provide credentials for a user that has used the manager mfa option
      And I have logged in
    Then I should see a message encouraging me to review my profile
      And I should not see any manager mfa information
