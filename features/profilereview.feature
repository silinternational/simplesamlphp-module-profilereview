Feature: Prompt to review profile information

  Scenario: Don't ask for review
    Given I provide credentials that do not need review
    When I login
    Then I should end up at my intended destination

  Scenario Outline: Present reminder as required by the user profile
    Given I provide credentials that are due for a <category> <nag type> reminder
    When I login
    Then I should see a message encouraging me to review my profile
    And there should be a way to go update my profile now
    And there should be a way to continue to my intended destination

    Examples:
      | category | nag type |
      | mfa      | add      |
      | method   | add      |
      | mfa      | review   |
      | method   | review   |

  Scenario Outline: Obeying a reminder
    Given I provide credentials that are due for a <category> <nag type> reminder
    And I have logged in
    When I click the update profile button
    Then I should end up at the update profile URL

    Examples:
      | category | nag type |
      | mfa      | add      |
      | method   | add      |
      | mfa      | review   |
      | method   | review   |

  Scenario Outline: Ignoring a reminder
    Given I provide credentials that are due for a <category> <nag type> reminder
    And I have logged in
    When I click the remind-me-later button
    Then I should end up at my intended destination

    Examples:
      | category | nag type |
      | mfa      | add      |
      | method   | add      |
      | mfa      | review   |
      | method   | review   |

