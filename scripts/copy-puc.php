<?php
/**
 * Copies Plugin Update Checker from vendor/ into lib/ for plugin distribution.
 *
 * Run automatically via Composer post-install-cmd and post-update-cmd hooks.
 * This keeps vendor/ out of the distributed plugin zip while still shipping PUC.
 */

$root = dirname( __DIR__ );
$src  = $root . '/vendor/yahnis-elsts/plugin-update-checker';
$dst  = $root . '/lib/plugin-update-checker';

if ( ! is_dir( $src ) ) {
	echo "PUC source not found at {$src} — skipping.\n";
	exit( 0 );
}

if ( is_dir( $dst ) ) {
	remove_dir( $dst );
}

copy_dir( $src, $dst );
echo "Copied Plugin Update Checker to lib/plugin-update-checker\n";

/**
 * Recursively copies a directory.
 */
function copy_dir( string $src, string $dst ): void {
	mkdir( $dst, 0755, true );
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $iterator as $item ) {
		$target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
		if ( $item->isDir() ) {
			mkdir( $target, 0755, true );
		} else {
			copy( $item->getPathname(), $target );
		}
	}
}

/**
 * Recursively removes a directory.
 */
function remove_dir( string $dir ): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}
	rmdir( $dir );
}
