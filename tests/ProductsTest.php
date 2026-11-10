<?php
use PHPUnit\Framework\TestCase;

final class ProductsTest extends TestCase {

	public function test_unlock_question_goes_to_the_shared_client(): void {
		$GLOBALS['mlab_core_unlocked'] = array( 'mlab-seo-pro' );
		$this->assertTrue( mlab_core_product_unlocked( 'mlab-seo-pro' ) );
		$this->assertFalse( mlab_core_product_unlocked( 'mlab-hub' ) );
	}
}
