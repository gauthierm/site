<?php

/**
 * Test contact us form
 *
 * @package   Site
 * @copyright 2012-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteContactUsTest extends TuringSeleniumTest
{
    // {{{ abstract protected function getPageUri()

    abstract protected function getPageUri();

    // }}}
    // {{{ abstract protected function getSubjectCount()

    abstract protected function getSubjectCount();

    // }}}
    // {{{ protected function getThankYouMessageContent()

    protected function getThankYouMessageContent()
    {
        return 'Thank You For Your Message';
    }

    // }}}

    // tests
    // {{{ public function testPageLoad()

    public function testPageLoad()
    {
        $this->open($this->getPageUri());

        $this->assertNoErrors();

        $this->assertTrue(
            $this->isTextPresent('Send us a Message'),
            'Contact form title is missing.'
        );
    }

    // }}}
    // {{{ public function testSubjectList()

    public function testSubjectList()
    {
        $this->open($this->getPageUri());

        $options = $this->getSelectOptions('id=subject');

        $this->assertEquals(
            $this->getSubjectCount(),
            count($options),
            'Number of contact subjects is incorrect.'
        );
    }

    // }}}
    // {{{ public function testEmptyForm()

    public function testEmptyForm()
    {
        $this->open($this->getPageUri());

        $this->click("xpath=//input[@type='submit' and @value='Send Message']");
        $this->waitForPageToLoad(30000);

        $this->assertNoErrors();

        $this->assertTrue(
            $this->isTextPresent('Your Email is required.'),
            'Email address required message not present.'
        );

        $this->assertTrue(
            $this->isTextPresent('Message is required.'),
            'Message required message not present.'
        );
    }

    // }}}
    // {{{ public function testBadEmail()

    public function testBadEmail()
    {
        $this->open($this->getPageUri());

        $this->type('email', 'invalid email address');
        $this->click("xpath=//input[@type='submit' and @value='Send Message']");
        $this->waitForPageToLoad(30000);

        $this->assertNoErrors();

        $this->assertTrue(
            $this->isTextPresent(
                'The email address you have entered is not properly formatted.'
            ),
            'Email validation error message not present.'
        );
    }

    // }}}
    // {{{ public function testFormProcessing()

    public function testFormProcessing()
    {
        $this->open($this->getPageUri());

        $this->type('email', 'nick@silverorange.com');
        $this->type('message', 'Test contact from Selenium.');
        $this->click("xpath=//input[@type='submit' and @value='Send Message']");
        $this->waitForPageToLoad(30000);

        $this->assertNoErrors();

        $this->assertTrue(
            $this->isTextPresent($this->getThankYouMessageContent()),
            'Thank you message not present after submitting contact message.'
        );
    }

    // }}}
}
