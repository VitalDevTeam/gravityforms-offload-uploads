<?php
// phpcs:ignoreFile
if (!defined('ABSPATH')) exit;

GFForms::include_addon_framework();

class GF_Offload_S3_Uploads extends GFAddOn {

	protected $_version = GF_SIMPLE_ADDON_VERSION;
	protected $_min_gravityforms_version = '2.4';
	protected $_slug = 'offloads3';
	protected $_path = 'gravityforms-offload-s3-uploads/gravityforms-offload-s3-uploads.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Offload S3 Uploads';
	protected $_short_title = 'Offload S3';

	private static $_instance = null;

	private $awsaccesskey;
	private $awssecretkey;
	private $s3bucketname;
	private $s3bucketpath;
	private $s3filepermissions;

	/**
	 * Get an instance of this class.
	 *
	 * @return GF_Offload_S3_Uploads
	 */
	public static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new GF_Offload_S3_Uploads();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();

		if ($plugin_options = get_option('gravityformsaddon_offloads3_settings')) {
			$this->awsaccesskey = $plugin_options['awsaccesskey'];
			$this->awssecretkey = $plugin_options['awssecretkey'];
			$this->s3bucketname = $plugin_options['s3bucketname'];
			$this->s3bucketpath = $plugin_options['s3bucketpath'];
			$this->s3filepermissions = $plugin_options['s3filepermissions'];
		}

		add_action('gform_field_standard_settings', [$this, 'upload_field_setting'], 10, 2);
		add_action('gform_editor_js', [$this, 'upload_field_setting_js']);
		add_action('gform_after_submission', [$this, 'after_submission'], 10, 2);
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return [
			[
				'title'  => 'Offload S3 Settings',
				'fields' => [
					[
						'name'  => 'awsaccesskey',
						'label' => 'AWS Access Key',
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'  => 'awssecretkey',
						'label' => 'AWS Secret Key',
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'  => 's3bucketname',
						'label' => 'S3 Bucket Name',
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'    => 's3bucketpath',
						'label'   => 'S3 Bucket Folder',
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => 'Full folder path within your bucket. Do not use a leading slash.',
					],
					[
						'name'          => 's3filepermissions',
						'label'         => 'S3 File Permissions',
						'type'          => 'select',
						'tooltip'       => 'Make sure your user/bucket permissions are correctly configured in AWS so that they work with your selected option. If your uploads are failing, this setting may be why.',
						'choices'       => [
							[
								'label' => 'Public Read',
								'value' => 'public-read',
							],
							[
								'label' => 'Public Read/Write',
								'value' => 'public-read-write',
							],
							[
								'label' => 'Private',
								'value' => 'private',
							],
							[
								'label' => 'Authenticated Read',
								'value' => 'authenticated-read',
							],
						],
						'default_value' => 'private',
					],
				],
			],
		];
	}

	/**
	 * Prints custom setting to field
	 *
	 * @param integer $index Specify the position that the settings should be displayed.
	 * @param integer $form_id The ID of the form from which the entry value was submitted.
	 */
	public function upload_field_setting($index, $form_id) {
		if ($index === 1600) {
			printf(
				'<li class="offloads3_setting field_setting"><label for="field_admin_label" class="section_label">%s</label><input type="checkbox" id="offloads3_setting" onclick="SetFieldProperty(\'offloadS3\', this.checked);"> Enable<div id="gform_offloads3_notice"><small>%s</small></div></li>',
				'Offload file(s) to Amazon S3',
				'Local copies of files will be deleted after successful transfer'
			);
		}
	}

	/**
	 * Prints JavaScript that handles custom setting on file upload fields
	 */
	public function upload_field_setting_js() {
		?>
		<script type='text/javascript'>
		(function($) {
			$(document).on('gform_load_field_settings', function(event, field, form) {
				$('#offloads3_setting').attr('checked', field['offloadS3'] === true);
				if (GetInputType(field) === 'fileupload' ) {
					$('.offloads3_setting').show();
				} else {
					$('.offloads3_setting').hide();
				}
			} );
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Returns absolute path of file from URL
	 *
	 * @param string $file File URL
	 * @param integer $field_id Field ID that owns this file (so we can update the URL later)
	 * @return string File path
	 */
	public function get_file_info($file) {
		$file_url = parse_url($file);
		$file_path = untrailingslashit(ABSPATH) . $file_url['path'];
		$file_name = basename($file_url['path']);
		return [
			'path'     => $file_path,
			'name'     => $file_name,
		];
	}

	/**
	 * Uploads file to Amazon S3
	 *
	 * @param string $file File URL
	 * @return array Array containing status of upload and new file as S3 object if successful
	 */
	public function upload_file_s3($file) {
		$file_info = $this->get_file_info($file);
		$file_name = $file_info['name'];

		if ($this->s3bucketpath) {
			$file_name = trailingslashit($this->s3bucketpath) . $file_name;
		}

		$s3 = new S3($this->awsaccesskey, $this->awssecretkey);
		$s3->putBucket($this->s3bucketname, $this->s3filepermissions);
		$s3->putObjectFile($file_info['path'], $this->s3bucketname, $file_name, $this->s3filepermissions);

		if ($s3->putObjectFile($file_info['path'], $this->s3bucketname, $file_name, $this->s3filepermissions)) {

			if (file_exists($file_info['path'])) {
				unlink($file_info['path']);
			}

			return [
				'success' => true,
				'file'    => S3::getObject($this->s3bucketname, $file_name)
			];

		} else {

			error_log(sprintf(
				'%s: There was an error uploading the file \'%s\'. Make sure your AWS credentials are correct and that access control on your bucket is correctly configured.',
				$this->_title,
				$file_info['name']
			));

			return [
				'success' => false,
			];
		}
	}

	/**
	 * Processes file upload fields
	 *
	 * @param object $entry The entry that was just created.
	 * @param object $form The current form.
	 */
	public function after_submission($entry, $form) {

		foreach ($form['fields'] as $field) {

			if ($field->type === 'fileupload' && $field->offloadS3 === true) {

				if ($field->multipleFiles === true) {

					$new_files = [];

					if ($files = json_decode(rgar($entry, $field->id))) {

						foreach ($files as $file) {
							$upload = $this->upload_file_s3($file);
							if ($upload['success'] === true) {
								$new_files[] = $upload['file']->url;
							}
						}

						if (!empty($new_files)) {
							gform_update_meta($entry['id'], $field->id, json_encode($new_files));
						}
					}

				} else {

					if ($file = rgar($entry, $field->id)) {
						$upload = $this->upload_file_s3($file);
						if ($upload['success'] === true) {
							gform_update_meta($entry['id'], $field->id, $upload['file']->url);
						}
					}
				}
			}
		}
	}
}
