<?php
namespace WEM\Content_Fields;

use WEM\Content_Fields\Admin\Admin;
use WEM\Content_Fields\Admin\Meta_Boxes;
use WEM\Content_Fields\Admin\Tools;
use WEM\Content_Fields\Field_Types\Checkbox;
use WEM\Content_Fields\Field_Types\Editor;
use WEM\Content_Fields\Field_Types\File;
use WEM\Content_Fields\Field_Types\Image;
use WEM\Content_Fields\Field_Types\Select;
use WEM\Content_Fields\Field_Types\Text;
use WEM\Content_Fields\Field_Types\Textarea;
use WEM\Content_Fields\Transfer\Exporter;
use WEM\Content_Fields\Transfer\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	public function boot(): void {
		$storage    = new Storage();
		$normalizer = new Normalizer();
		$types      = new Field_Type_Registry();

		$types->register( new Text() );
		$types->register( new Textarea() );
		$types->register( new Editor() );
		$types->register( new Image() );
		$types->register( new File() );
		$types->register( new Select() );
		$types->register( new Checkbox() );

		$validator   = new Validator( $types );
		$definitions = new Definitions( $storage, $normalizer, $validator );
		$registry    = new Registry( $definitions, $types );
		$registry->hooks();

		if ( is_admin() ) {
			$admin = new Admin( $storage, $normalizer, $validator, $types );
			$admin->hooks();

			$exporter = new Exporter( $storage );
			$importer = new Importer( $storage, $normalizer, $validator );
			$tools    = new Tools( $storage, $exporter, $importer );
			$tools->hooks();

			$meta_boxes = new Meta_Boxes( $definitions, $types );
			$meta_boxes->hooks();
		}
	}
}
