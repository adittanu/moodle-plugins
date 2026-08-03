<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_siteframe;

class domain_helper_test extends \advanced_testcase {
    public function test_url_and_allowlist_contract(): void {
        $this->resetAfterTest();
        set_config('allowed_domains', "example.com\nhttps://trusted.test/path", 'local_siteframe');

        $this->assertSame('https://sub.example.com/path', domain_helper::sanitize_url(' https://sub.example.com/path '));
        $this->assertTrue(domain_helper::is_domain_allowed('https://sub.example.com/path'));
        $this->assertTrue(domain_helper::is_domain_allowed('https://trusted.test/page'));
        $this->assertFalse(domain_helper::is_domain_allowed('https://example.com.invalid/path'));
        $this->assertFalse(domain_helper::sanitize_url('javascript:alert(1)'));
    }

    public function test_css_dimensions_reject_unsafe_values(): void {
        $this->assertSame('800px', domain_helper::sanitize_css_dimension('800px'));
        $this->assertSame('100%', domain_helper::sanitize_css_dimension('calc(100% - 1px)'));
    }
}
