@paygw @paygw_hikmahtransfer @_file_upload
Feature: Pay for a course by manual transfer
  In order to join a paid Hikmah course
  As a learner
  I need to submit a transfer receipt and get access only after a reviewer approves it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | learner1 | Aisha     | Khan     | aisha@example.com   |
      | finance1 | Bilal     | Ahmed    | finance@example.com |
      | teacher1 | Hamza     | Malik    | teacher@example.com |
    And the following "roles" exist:
      | shortname | name             | archetype |
      | finance   | Finance reviewer |           |
    And the following "role capability" exists:
      | role                             | finance |
      | paygw/hikmahtransfer:review      | allow   |
      | paygw/hikmahtransfer:viewreceipt | allow   |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | finance1 | finance | System       |           |
    And the following "core_payment > payment accounts" exist:
      | name   | gateways       |
      | Hikmah | hikmahtransfer |
    And the following "courses" exist:
      | fullname      | shortname |
      | Paid course 1 | PAID1     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | PAID1  | editingteacher |
    And the following config values are set as admin:
      | paygw_plugins_sortorder | hikmahtransfer |
    And I log in as "admin"
    And I navigate to "General > Payments > Payment accounts" in site administration
    And I click on "Hikmah transfer" "link" in the "Hikmah" "table_row"
    And I set the field "IBAN / account number" to "PK00HKMH0000000000000000"
    And I press "Save changes"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Enrolment on payment" "table_row"
    And I add "Enrolment on payment" enrolment method in "Paid course 1" with:
      | Payment account | Hikmah         |
      | Enrolment fee   | 4500           |
      | Currency        | Pakistan Rupee |
    And I log out

  @javascript
  Scenario: Submit a receipt, then a reviewer approves and the course opens
    Given I log in as "learner1"
    And I am on "Paid course 1" course homepage
    When I click on "Select payment type" "button"
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    Then I should see "Pay by transfer"
    And I should see "Payment reference"
    When I set the following fields to these values:
      | Transaction ID | TX-1001 |
    And I upload "payment/gateway/hikmahtransfer/tests/fixtures/receipt.png" file to "Receipt" filemanager
    And I press "Submit receipt"
    Then I should see "Receipt received"
    And I am on "Paid course 1" course homepage
    And I should see "Select payment type"
    And I log out
    When I log in as "finance1"
    And I visit "/payment/gateway/hikmahtransfer/review.php"
    And I click on "Review" "link" in the "Aisha Khan" "table_row"
    And I press "Approve and enrol"
    Then I should see "Payment approved"
    And I log out
    When I log in as "learner1"
    And I am on "Paid course 1" course homepage
    Then I should not see "Select payment type"

  @javascript
  Scenario: A rejected request can be resubmitted after the reviewer asks for it
    Given I log in as "learner1"
    And I am on "Paid course 1" course homepage
    And I click on "Select payment type" "button"
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I set the following fields to these values:
      | Transaction ID | TX-2002 |
    And I upload "payment/gateway/hikmahtransfer/tests/fixtures/receipt.png" file to "Receipt" filemanager
    And I press "Submit receipt"
    And I log out
    When I log in as "finance1"
    And I visit "/payment/gateway/hikmahtransfer/review.php"
    And I click on "Review" "link" in the "Aisha Khan" "table_row"
    And I press "Ask to resubmit"
    Then I should see "Give a reason"
    When I set the field "Remarks for the learner" to "The image is unreadable"
    And I press "Ask to resubmit"
    Then I should see "asked to resubmit"
    And I log out
    When I log in as "learner1"
    And I visit "/payment/gateway/hikmahtransfer/status.php"
    And I follow "Paid course 1"
    Then I should see "Please resubmit your receipt"
    And I should see "The image is unreadable"

  Scenario: A teacher cannot open the review queue
    Given I log in as "teacher1"
    When I visit "/payment/gateway/hikmahtransfer/review.php"
    Then I should see "Sorry, but you do not currently have permissions to do that"
