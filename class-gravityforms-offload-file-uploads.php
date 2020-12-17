<?php
// phpcs:ignoreFile
if (!defined('ABSPATH')) exit;

GFForms::include_addon_framework();

class GF_Offload_File_Uploads extends GFAddOn {

	protected $_version = GF_SIMPLE_ADDON_VERSION;
	protected $_min_gravityforms_version = '2.4';
	protected $_slug = 'offload_file_uploads';
	protected $_path = 'gravityforms-offload-file-uploads/gravityforms-offload-file-uploads.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Offload File Uploads';
	protected $_short_title = 'Offload File Uploads';

	private static $_instance = null;

	private $awsaccesskey;
	private $awssecretkey;
	private $s3bucketname;
	private $s3bucketpath;
	private $s3filepermissions;

	private $ftphost;
	private $ftpport;
	private $ftpconnectiontion;
	private $ftpuser;
	private $ftppasswordrd;
	private $ftppath;
	private $ftpurl;

	/**
	 * Get an instance of this class.
	 *
	 * @since 1.0.0
	 * @return GF_Offload_File_Uploads
	 */
	public static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new GF_Offload_File_Uploads();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		parent::init();

		if ($plugin_options = get_option('gravityformsaddon_offload_file_uploads_settings')) {
			$this->awsaccesskey = $plugin_options['awsaccesskey'];
			$this->awssecretkey = $plugin_options['awssecretkey'];
			$this->s3bucketname = $plugin_options['s3bucketname'];
			$this->s3bucketpath = $plugin_options['s3bucketpath'];
			$this->s3filepermissions = $plugin_options['s3filepermissions'];

			$this->ftphost = $plugin_options['ftphost'];
			$this->ftpport = $plugin_options['ftpport'];
			$this->ftpconnection = $plugin_options['ftpconnection'];
			$this->ftpuser = $plugin_options['ftpuser'];
			$this->ftppassword = $plugin_options['ftppassword'];
			$this->ftppath = $plugin_options['ftppath'];
			$this->ftpurl = $plugin_options['ftpurl'];
		}

		add_action('gform_field_standard_settings', [$this, 'upload_field_setting'], 10, 2);
		add_action('gform_editor_js', [$this, 'upload_field_setting_js']);
		add_action('gform_after_submission', [$this, 'after_submission'], 10, 2);
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @since 1.0.0
	 * @return array Array of settings fields.
	 */
	public function plugin_settings_fields() {
		return [
			[
				'title'  => esc_html__('Amazon S3 Settings', 'vital'),
				'fields' => [
					[
						'name'  => 'awsaccesskey',
						'label' => esc_html__('AWS Access Key', 'vital'),
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'  => 'awssecretkey',
						'label' => esc_html__('AWS Secret Key', 'vital'),
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'  => 's3bucketname',
						'label' => esc_html__('S3 Bucket Name', 'vital'),
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'    => 's3bucketpath',
						'label'   => esc_html__('S3 Bucket Folder', 'vital'),
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => esc_html__('Full folder path within your bucket. Do not use a leading slash.', 'vital'),
					],
					[
						'name'          => 's3filepermissions',
						'label'         => esc_html__('S3 File Permissions', 'vital'),
						'type'          => 'select',
						'tooltip'       => esc_html__('Make sure your user/bucket permissions are correctly configured in AWS so that they work with your selected option. If your uploads are failing, this setting may be why.', 'vital'),
						'choices'       => [
							[
								'label' => esc_html__('Public Read', 'vital'),
								'value' => 'public-read',
							],
							[
								'label' => esc_html__('Private', 'vital'),
								'value' => 'private',
							],
							[
								'label' => esc_html__('Authenticated Read', 'vital'),
								'value' => 'authenticated-read',
							],
						],
						'default_value' => 'private',
					],
				],
			],
			[
				'title'  => 'FTP Settings',
				'fields' => [
					[
						'name'        => 'ftphost',
						'label'       => esc_html__('FTP Hostname', 'vital'),
						'type'        => 'text',
						'class'       => 'medium',
						'placeholder' => 'ftp.example.com',
					],
					[
						'name'    => 'ftpport',
						'label'   => esc_html__('FTP Port Number', 'vital'),
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => esc_html__('Set custom FTP port. Defaults to port 21 for FTP and port 22 for SFTP if not set.', 'vital'),
					],
					[
						'name'  => 'ftpconnection',
						'label' => esc_html__('FTP Connection Type', 'vital'),
						'type'  => 'select',
						'class' => 'medium',
						'choices'       => [
							[
								'label' => esc_html__('FTP with Explicit TLS', 'vital'),
								'value' => 'ftp-tls',
							],
						],
						'default_value' => 'ftp-tls',
					],
					[
						'name'  => 'ftpuser',
						'label' => esc_html__('FTP Username', 'vital'),
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'  => 'ftppassword',
						'label' => esc_html__('FTP Password', 'vital'),
						'type'  => 'text',
						'class' => 'medium',
					],
					[
						'name'        => 'ftppath',
						'label'       => esc_html__('FTP Remote Path', 'vital'),
						'type'        => 'text',
						'class'       => 'medium',
						'placeholder' => '/example',
						'tooltip'     => esc_html__('Absolute folder path on FTP server where the files should be uploaded.', 'vital'),
					],
					[
						'name'        => 'ftpurl',
						'label'       => esc_html__('FTP Root URL', 'vital'),
						'type'        => 'text',
						'class'       => 'medium',
						'placeholder' => 'https://example.com',
						'tooltip'     => esc_html__('Root URL where FTP remote path is accessible. Used to generate download links in form entries. Be sure to include http:// or https://.', 'vital'),
					],
				],
			],
		];
	}

	/**
	 * Prints custom setting to field.
	 *
	 * @since 1.0.0
	 * @param integer $index Specify the position that the settings should be displayed.
	 * @param integer $form_id The ID of the form from which the entry value was submitted.
	 */
	public function upload_field_setting($index, $form_id) {
		if ($index === 1600) {

			printf(
				'<li class="offload-file-uploads-setting field_setting">
					<label for="field_admin_label" class="section_label">
						%1$s
						<a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_form_field_offloads3" title="<h6>%1$s</h6>%2$s"><i class="fa fa-question-circle"></i></a>
					</label>
					<input class="offload-file-uploads-checkbox" name="offloads3_setting" type="checkbox" id="offloads3_setting" onclick="SetFieldProperty(\'offloadS3\', this.checked);">
					<label for="offloads3_setting" class="inline">%3$s</label>
				</li>',
				esc_html__('Offload files to Amazon S3', 'vital'),
				esc_html__('Enable this option to offload uploaded files in this field to Amazon S3. Local copies of files will be deleted after successful transfer', 'vital'),
				esc_html__('Enable', 'vital')
			);

			printf(
				'<li class="offload-file-uploads-setting field_setting">
					<label for="field_admin_label" class="section_label">
						%1$s
						<a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_form_field_offloadftp" title="<h6>%1$s</h6>%2$s"><i class="fa fa-question-circle"></i></a>
					</label>
					<input class="offload-file-uploads-checkbox" name="offloadftp_setting" type="checkbox" id="offloadftp_setting" onclick="SetFieldProperty(\'offloadFtp\', this.checked);">
					<label for="offloadftp_setting" class="inline">%3$s</label>
				</li>',
				esc_html__('Offload files to FTP storage', 'vital'),
				esc_html__('Enable this option to offload uploaded files in this field to FTP storage. Local copies of files will be deleted after successful transfer', 'vital'),
				esc_html__('Enable', 'vital')
			);
		}
	}

	/**
	 * Prints JavaScript that handles custom setting on file upload fields.
	 *
	 * @since 1.0.0
	 */
	public function upload_field_setting_js() {
		?>
		<script type='text/javascript'>
		(function($) {
			$(document).on('gform_load_field_settings', function(event, field, form) {

				$('#offloads3_setting').attr('checked', field['offloadS3'] === true);
				$('#offloadftp_setting').attr('checked', field['offloadFtp'] === true);

				if (GetInputType(field) === 'fileupload' ) {
					$('.offload-file-uploads-setting').show();
				} else {
					$('.offload-file-uploads-setting').hide();
				}
			} );
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Returns absolute path of file from URL.
	 *
	 * @since 1.0.0
	 * @param  string $file File URL.
	 * @param  integer $field_id Field ID that owns this file (so we can update the URL later)
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
	 * Uploads file to Amazon S3.
	 *
	 * @since 1.0.0
	 * @param  array $files File URLs to upload.
	 * @return array Remote file URLs.
	 */
	public function upload_files_s3($files) {

		if ($this->awsaccesskey === ''
			|| $this->awssecretkey === ''
			|| $this->s3bucketname === '') {

			error_log(sprintf(
				'%s: Required Amazon S3 credentials are missing in the plugin settings. Make sure you have set your AWS access key, secret key, and S3 bucket name.',
				$this->_title,
			));

			return false;
		}

		$remote_files = [];
		$s3 = new S3($this->awsaccesskey, $this->awssecretkey);
		$s3->putBucket($this->s3bucketname, $this->s3filepermissions);

		foreach ($files as $key => $file) {
			$file_info = $this->get_file_info($file);
			$file_name = $file_info['name'];

			if ($this->s3bucketpath) {
				$file_name = trailingslashit($this->s3bucketpath) . $file_name;
			}

			if ($s3->putObjectFile($file_info['path'], $this->s3bucketname, $file_name, $this->s3filepermissions)) {

				if (file_exists($file_info['path'])) {
					unlink($file_info['path']);
				}

				$remote_file = S3::getObject($this->s3bucketname, $file_name);
				$remote_files[] = $remote_file->url;

			} else {

				error_log(sprintf(
					'%s: There was an error uploading the file \'%s\'. Make sure your AWS credentials are correct and that access control on your bucket is correctly configured.',
					$this->_title,
					$file_info['name']
				));
			}
		}

		return $remote_files;
	}

	/**
	 * Uploads files to FTP storage.
	 *
	 * @since 1.0.0
	 * @param  array $files File URLs to upload.
	 * @return array Remote file names.
	 */
	public function upload_files_ftp($files) {

		if (empty($this->ftphost) || empty($this->ftpuser) || empty($this->ftppassword)) {

			error_log(sprintf(
				'%s: Required FTP credentials are missing in the plugin settings. Make sure you have set your FTP hostname, username, and password.',
				$this->_title,
			));

			return false;
		}

		if (empty($this->ftpport)) {
			switch ($this->ftpconnection) {
				case 'sftp':
					$this->ftpport = '22';
					break;

				default:
					$this->ftpport = '21';
					break;
			}
		}

		$url_base = '';

		if (!empty($this->ftpurl)) {

			$url_base = trailingslashit($this->ftpurl);

		} elseif (!empty($this->ftphost) && !empty($this->ftppath)) {

			$url_base = sprintf(
				'ftp://%s/%s',
				untrailingslashit($this->ftphost),
				ltrim(trailingslashit($this->ftppath), '/')
			);
		}

		$remote_files = [];
		$ftp_conn = ftp_ssl_connect($this->ftphost, $this->ftpport);
		$login_result = ftp_login($ftp_conn, $this->ftpuser, $this->ftppassword);

		if ($login_result) {
			ftp_pasv($ftp_conn, true);
			$contents_on_server = ftp_nlist($ftp_conn, $this->ftppath);

			foreach ($files as $key => $file) {
				$file_info = $this->get_file_info($file);
				$file_name = $file_info['name'];
				$local_file = $file_info['path'];

				// If file already exists on remote, add timestamp to file name
				if (in_array($file_name, $contents_on_server)) {
					$rename_file = pathinfo($file_name);
					$file_name = $rename_file['filename'] . '_' . time() . '.' . $rename_file['extension'];
				}

				if (!empty($this->ftppath)) {
					$remote_file = trailingslashit($this->ftppath) . $file_name;
				} else {
					$remote_file = $file_name;
				}

				$transfer_result = ftp_put($ftp_conn, $remote_file, $local_file, FTP_BINARY);

				if ($transfer_result) {

					if (file_exists($file_info['path'])) {
						unlink($file_info['path']);
					}

					$remote_files[] = $url_base . $file_name;

				} else {

					error_log(sprintf(
						'%s: There was an error uploading the file \'%s\'. Make sure your FTP credentials are correct in the plugin settings.',
						$this->_title,
						$file_name
					));
				}
			}
		}

		ftp_close($ftp_conn);

		return $remote_files;
	}

	/**
	 * Processes file upload fields.
	 *
	 * @since 1.0.0
	 * @param object $entry The entry that was just created.
	 * @param object $form The current form.
	 */
	public function after_submission($entry, $form) {

		foreach ($form['fields'] as $field) {

			if ($field->type === 'fileupload') {

				if ($files = rgar($entry, $field->id)) {

					if ($field->multipleFiles === true) {
						$files = json_decode($files);
					} else {
						$files = [$files];
					}

					if (isset($field->offloadS3) && $field->offloadS3 === true) {
						$remote_files = $this->upload_files_s3($files);
					}

					if (isset($field->offloadFtp) && $field->offloadFtp === true) {
						$remote_files = $this->upload_files_ftp($files);
					}

					if (isset($remote_files) && !empty($remote_files)) {

						if ($field->multipleFiles) {
							$remote_files = json_encode($remote_files);
						} else {
							$remote_files = $remote_files[0];
						}

						gform_update_meta($entry['id'], $field->id, $remote_files);
					}
				}
			}
		}
	}
}
