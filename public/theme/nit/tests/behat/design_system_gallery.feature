@theme @theme_nit
Feature: NIT design system gallery
  In order to review the NIT design system
  As an administrator
  I need the component gallery to render every foundation component

  Scenario: The gallery renders the token and component showcase
    Given I log in as "admin"
    When I visit "/theme/nit/gallery.php"
    Then I should see "Colour"
    And I should see "Buttons"
    And I should see "Statistics"
    And "Primary" "button" should exist
    And I should not see "Exception"
