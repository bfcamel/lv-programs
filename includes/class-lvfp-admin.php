<?php
/**
 * WordPress administration for foundation programs.
 *
 * @package LVPrograms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LVFP_Admin {
	/** @var LVFP_Admin|null */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return LVFP_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register admin hooks.
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . LVFP_Plugin::POST_TYPE, array( $this, 'save_program_meta' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . LVFP_Plugin::POST_TYPE . '_posts_columns', array( $this, 'program_columns' ) );
		add_action( 'manage_' . LVFP_Plugin::POST_TYPE . '_posts_custom_column', array( $this, 'program_column_content' ), 10, 2 );
	}

	/**
	 * Add the compact program editor.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'lvfp-program-settings',
			'Данные программы',
			array( $this, 'render_program_meta_box' ),
			LVFP_Plugin::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Keep the program form compact and independent from Gutenberg.
	 *
	 * @param bool   $use_block_editor Current editor decision.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function disable_block_editor( $use_block_editor, $post_type ) {
		return LVFP_Plugin::POST_TYPE === $post_type ? false : $use_block_editor;
	}

	/**
	 * Clarify the native title field.
	 *
	 * @param string  $text Current placeholder.
	 * @param WP_Post|null $post Current post.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		return $post instanceof WP_Post && LVFP_Plugin::POST_TYPE === $post->post_type ? 'Название программы' : $text;
	}

	/**
	 * Render program settings.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_program_meta_box( $post ) {
		$label       = (string) get_post_meta( $post->ID, '_lvfp_label', true );
		$description = metadata_exists( 'post', $post->ID, '_lvfp_description' )
			? (string) get_post_meta( $post->ID, '_lvfp_description', true )
			: $post->post_content;
		$image_id    = absint( get_post_meta( $post->ID, '_lvfp_image_id', true ) );
		$image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';

		wp_nonce_field( 'lvfp_save_program', 'lvfp_program_nonce' );
		?>
		<div class="lvfp-program-fields">
			<div class="lvfp-program-field">
				<label for="lvfp-description"><strong>Описание</strong></label>
				<textarea class="widefat" id="lvfp-description" name="lvfp_description" rows="10" placeholder="Расскажите, какую помощь оказывает программа"><?php echo esc_textarea( $description ); ?></textarea>
			</div>

			<div class="lvfp-program-field">
				<label for="lvfp-label"><strong>Рубрика</strong></label>
				<input class="widefat" id="lvfp-label" name="lvfp_label" type="text" value="<?php echo esc_attr( $label ); ?>" placeholder="Например: Реабилитация">
				<p class="description">Короткая цветная надпись над названием карточки.</p>
			</div>

			<div class="lvfp-program-field lvfp-image-control">
				<label><strong>Фотография</strong></label>
				<div class="lvfp-image-preview<?php echo $image_url ? '' : ' is-empty'; ?>">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="">
					<?php endif; ?>
				</div>
				<input class="lvfp-image-id" type="hidden" name="lvfp_image_id" value="<?php echo esc_attr( $image_id ); ?>">
				<p>
					<button class="button lvfp-select-image" type="button">Выбрать изображение</button>
					<button class="button-link-delete lvfp-remove-image" type="button"<?php echo $image_id ? '' : ' hidden'; ?>>Удалить</button>
				</p>
			</div>
		</div>
		<p class="lvfp-program-placement-note">Порядок и видимость на каждой странице настраиваются отдельно в разделе <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . LVFP_Plugin::POST_TYPE . '&page=lvfp-order' ) ); ?>">«Порядок и показ»</a>.</p>
		<?php
	}

	/**
	 * Save program-specific fields.
	 *
	 * @param int $post_id Program ID.
	 */
	public function save_program_meta( $post_id ) {
		if ( ! isset( $_POST['lvfp_program_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lvfp_program_nonce'] ) ), 'lvfp_save_program' )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$label       = isset( $_POST['lvfp_label'] ) ? sanitize_text_field( wp_unslash( $_POST['lvfp_label'] ) ) : '';
		$description = isset( $_POST['lvfp_description'] ) ? wp_kses_post( wp_unslash( $_POST['lvfp_description'] ) ) : '';

		update_post_meta( $post_id, '_lvfp_label', $label );
		update_post_meta( $post_id, '_lvfp_description', $description );
		update_post_meta( $post_id, '_lvfp_image_id', isset( $_POST['lvfp_image_id'] ) ? absint( $_POST['lvfp_image_id'] ) : 0 );
	}

	/**
	 * Add settings and ordering pages below the CPT menu.
	 */
	public function add_admin_pages() {
		$parent = 'edit.php?post_type=' . LVFP_Plugin::POST_TYPE;

		add_submenu_page(
			$parent,
			'Шапки блоков',
			'Шапки блоков',
			'manage_options',
			'lvfp-headers',
			array( $this, 'render_headers_page' )
		);

		add_submenu_page(
			$parent,
			'Порядок и показ программ',
			'Порядок и показ',
			'manage_options',
			'lvfp-order',
			array( $this, 'render_order_page' )
		);
	}

	/**
	 * Load admin assets only on plugin screens.
	 *
	 * @param string $hook_suffix Current screen hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || LVFP_Plugin::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'lvfp-admin', LVFP_URL . 'assets/css/admin.css', array(), LVFP_VERSION );

		if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) {
			wp_enqueue_media();
			wp_enqueue_script( 'lvfp-admin-program', LVFP_URL . 'assets/js/admin-program.js', array( 'jquery' ), LVFP_VERSION, true );
		}

		if ( 'lv_program_page_lvfp-order' === $hook_suffix ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'lvfp-admin-order', LVFP_URL . 'assets/js/admin-order.js', array( 'jquery', 'jquery-ui-sortable' ), LVFP_VERSION, true );
		}
	}

	/**
	 * Render headings settings page.
	 */
	public function render_headers_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = false;
		if ( isset( $_POST['lvfp_save_headers'] ) ) {
			check_admin_referer( 'lvfp_save_headers', 'lvfp_headers_nonce' );
			$headers = array(
				'home' => $this->sanitize_header_context( isset( $_POST['lvfp_headers']['home'] ) ? $_POST['lvfp_headers']['home'] : array(), 'home' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'page' => $this->sanitize_header_context( isset( $_POST['lvfp_headers']['page'] ) ? $_POST['lvfp_headers']['page'] : array(), 'page' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			);
			update_option( LVFP_Plugin::OPTION_HEADERS, $headers, false );
			$saved = true;
		}

		$plugin  = LVFP_Plugin::instance();
		$headers = array(
			'home' => $plugin->get_header( 'home' ),
			'page' => $plugin->get_header( 'page' ),
		);
		?>
		<div class="wrap lvfp-admin-wrap">
			<h1>Шапки блоков программ</h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>
			<?php endif; ?>
			<p>У каждого сниппета своя шапка. Любой из трёх элементов можно скрыть, не удаляя введённый текст.</p>

			<form method="post">
				<?php wp_nonce_field( 'lvfp_save_headers', 'lvfp_headers_nonce' ); ?>
				<div class="lvfp-admin-grid">
					<?php $this->render_header_fields( 'home', 'Главная страница', '[lv_programs_home]', $headers['home'] ); ?>
					<?php $this->render_header_fields( 'page', 'Страница программ', '[lv_programs_page]', $headers['page'] ); ?>
				</div>
				<?php submit_button( 'Сохранить шапки', 'primary', 'lvfp_save_headers' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one header configuration card.
	 *
	 * @param string $context Context key.
	 * @param string $title Card title.
	 * @param string $shortcode Shortcode help.
	 * @param array  $values Current values.
	 */
	private function render_header_fields( $context, $title, $shortcode, $values ) {
		$prefix = 'lvfp_headers[' . $context . ']';
		?>
		<section class="lvfp-admin-card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><code><?php echo esc_html( $shortcode ); ?></code></p>
			<div class="lvfp-admin-field">
				<label class="lvfp-admin-toggle"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[eyebrow_visible]' ); ?>" value="1" <?php checked( ! empty( $values['eyebrow_visible'] ) ); ?>> Показывать верхнюю надпись</label>
				<input class="widefat" type="text" name="<?php echo esc_attr( $prefix . '[eyebrow_text]' ); ?>" value="<?php echo esc_attr( $values['eyebrow_text'] ); ?>">
			</div>
			<div class="lvfp-admin-field">
				<label class="lvfp-admin-toggle"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[title_visible]' ); ?>" value="1" <?php checked( ! empty( $values['title_visible'] ) ); ?>> Показывать заголовок</label>
				<input class="widefat" type="text" name="<?php echo esc_attr( $prefix . '[title_text]' ); ?>" value="<?php echo esc_attr( $values['title_text'] ); ?>">
			</div>
			<div class="lvfp-admin-field">
				<label class="lvfp-admin-toggle"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[lead_visible]' ); ?>" value="1" <?php checked( ! empty( $values['lead_visible'] ) ); ?>> Показывать вводный текст</label>
				<textarea class="widefat" rows="5" name="<?php echo esc_attr( $prefix . '[lead_text]' ); ?>"><?php echo esc_textarea( $values['lead_text'] ); ?></textarea>
			</div>
			<?php if ( 'home' === $context ) : ?>
				<div class="lvfp-admin-field lvfp-admin-field--button">
					<h3>Кнопка под программами</h3>
					<label class="lvfp-admin-toggle"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[button_visible]' ); ?>" value="1" <?php checked( ! empty( $values['button_visible'] ) ); ?>> Показывать кнопку</label>
					<label for="lvfp-button-text"><strong>Текст кнопки</strong></label>
					<input class="widefat" id="lvfp-button-text" type="text" name="<?php echo esc_attr( $prefix . '[button_text]' ); ?>" value="<?php echo esc_attr( $values['button_text'] ); ?>">
					<label for="lvfp-button-url"><strong>Адрес страницы программ</strong></label>
					<input class="widefat" id="lvfp-button-url" type="text" name="<?php echo esc_attr( $prefix . '[button_url]' ); ?>" value="<?php echo esc_attr( $values['button_url'] ); ?>" placeholder="/programs/">
					<p class="description">Можно указать полный адрес или путь, начинающийся с /.</p>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Sanitize one header context from POST.
	 *
	 * @param mixed  $raw Raw request value.
	 * @param string $context home|page.
	 * @return array
	 */
	private function sanitize_header_context( $raw, $context ) {
		$raw = is_array( $raw ) ? wp_unslash( $raw ) : array();
		$result = array(
			'eyebrow_visible' => isset( $raw['eyebrow_visible'] ) ? 1 : 0,
			'eyebrow_text'    => isset( $raw['eyebrow_text'] ) ? sanitize_text_field( $raw['eyebrow_text'] ) : '',
			'title_visible'   => isset( $raw['title_visible'] ) ? 1 : 0,
			'title_text'      => isset( $raw['title_text'] ) ? sanitize_text_field( $raw['title_text'] ) : '',
			'lead_visible'    => isset( $raw['lead_visible'] ) ? 1 : 0,
			'lead_text'       => isset( $raw['lead_text'] ) ? sanitize_textarea_field( $raw['lead_text'] ) : '',
		);

		if ( 'home' === $context ) {
			$result['button_visible'] = isset( $raw['button_visible'] ) ? 1 : 0;
			$result['button_text']    = isset( $raw['button_text'] ) ? sanitize_text_field( $raw['button_text'] ) : '';
			$result['button_url']     = isset( $raw['button_url'] ) ? esc_url_raw( $raw['button_url'] ) : '';
		}

		return $result;
	}

	/**
	 * Render independent ordering lists.
	 */
	public function render_order_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = false;
		if ( isset( $_POST['lvfp_save_order'] ) ) {
			check_admin_referer( 'lvfp_save_order', 'lvfp_order_nonce' );
			$this->save_order_option( LVFP_Plugin::OPTION_HOME, isset( $_POST['lvfp_order_home'] ) ? $_POST['lvfp_order_home'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->save_order_option( LVFP_Plugin::OPTION_PAGE, isset( $_POST['lvfp_order_page'] ) ? $_POST['lvfp_order_page'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->save_visibility( 'home', isset( $_POST['lvfp_visible_home'] ) ? $_POST['lvfp_visible_home'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->save_visibility( 'page', isset( $_POST['lvfp_visible_page'] ) ? $_POST['lvfp_visible_page'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$saved = true;
		}

		$home = $this->get_admin_ordered_programs( LVFP_Plugin::OPTION_HOME );
		$page = $this->get_admin_ordered_programs( LVFP_Plugin::OPTION_PAGE );
		?>
		<div class="wrap lvfp-admin-wrap">
			<h1>Порядок и видимость программ</h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Порядок и видимость сохранены.</p></div>
			<?php endif; ?>
			<p>Перетаскивайте карточки за значок слева. Переключатель «Показывать» отвечает только за выбранную страницу. Порядок и видимость двух списков сохраняются независимо.</p>

			<form method="post" class="lvfp-order-form">
				<?php wp_nonce_field( 'lvfp_save_order', 'lvfp_order_nonce' ); ?>
				<div class="lvfp-admin-grid">
					<?php $this->render_order_list( 'home', 'Главная страница', '[lv_programs_home]', $home ); ?>
					<?php $this->render_order_list( 'page', 'Страница программ', '[lv_programs_page]', $page ); ?>
				</div>
				<?php submit_button( 'Сохранить настройки программ', 'primary', 'lvfp_save_order' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a sortable program list.
	 *
	 * @param string    $context home|page.
	 * @param string    $title List title.
	 * @param string    $shortcode Shortcode help.
	 * @param WP_Post[] $programs Ordered posts.
	 */
	private function render_order_list( $context, $title, $shortcode, $programs ) {
		$input_id = 'lvfp-order-' . $context;
		$ids      = wp_list_pluck( $programs, 'ID' );
		?>
		<section class="lvfp-admin-card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><code><?php echo esc_html( $shortcode ); ?></code></p>
			<input id="<?php echo esc_attr( $input_id ); ?>" type="hidden" name="lvfp_order_<?php echo esc_attr( $context ); ?>" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
			<ul class="lvfp-sortable" data-order-input="<?php echo esc_attr( $input_id ); ?>">
				<?php foreach ( $programs as $program ) : ?>
					<?php $this->render_order_item( $program, $context ); ?>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * Render a sortable list item.
	 *
	 * @param WP_Post $program Program post.
	 * @param string  $context home|page.
	 */
	private function render_order_item( $program, $context ) {
		$visibility_key = 'page' === $context ? '_lvfp_show_page' : '_lvfp_show_home';
		$visibility     = get_post_meta( $program->ID, $visibility_key, true );
		$is_visible     = '' === $visibility || '1' === $visibility;
		?>
		<li class="lvfp-sortable__item" data-program-id="<?php echo esc_attr( $program->ID ); ?>">
			<span class="dashicons dashicons-menu lvfp-sortable__handle" aria-hidden="true"></span>
			<span class="lvfp-sortable__title"><?php echo esc_html( get_the_title( $program ) ); ?></span>
			<?php if ( 'publish' !== $program->post_status ) : ?>
				<span class="lvfp-status"><?php echo esc_html( get_post_status_object( $program->post_status )->label ); ?></span>
			<?php endif; ?>
			<label class="lvfp-visibility">
				<input type="checkbox" name="lvfp_visible_<?php echo esc_attr( $context ); ?>[]" value="<?php echo esc_attr( $program->ID ); ?>" <?php checked( $is_visible ); ?>>
				<span>Показывать</span>
			</label>
		</li>
		<?php
	}

	/**
	 * Return all programs in the order saved for one context.
	 *
	 * @param string $option_name Order option.
	 * @return WP_Post[]
	 */
	private function get_admin_ordered_programs( $option_name ) {
		$posts = get_posts(
			array(
				'post_type'      => LVFP_Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
				'order'          => 'ASC',
			)
		);

		$by_id = array();
		foreach ( $posts as $post ) {
			$by_id[ $post->ID ] = $post;
		}

		$result = array();
		foreach ( array_map( 'absint', (array) get_option( $option_name, array() ) ) as $post_id ) {
			if ( isset( $by_id[ $post_id ] ) ) {
				$result[] = $by_id[ $post_id ];
				unset( $by_id[ $post_id ] );
			}
		}

		return array_merge( $result, array_values( $by_id ) );
	}

	/**
	 * Validate and save one ordering option.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $raw Raw comma-separated IDs.
	 */
	private function save_order_option( $option_name, $raw ) {
		$ids   = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $raw ) ) ) ) ) ) );
		$valid = array();

		foreach ( $ids as $post_id ) {
			if ( LVFP_Plugin::POST_TYPE === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
				$valid[] = $post_id;
			}
		}

		update_option( $option_name, $valid, false );
	}

	/**
	 * Save visibility for every program in one shortcode context.
	 *
	 * @param string $context home|page.
	 * @param mixed  $raw_visible Selected program IDs.
	 */
	private function save_visibility( $context, $raw_visible ) {
		$visible_ids = is_array( $raw_visible ) ? array_map( 'absint', wp_unslash( $raw_visible ) ) : array();
		$meta_key    = 'page' === $context ? '_lvfp_show_page' : '_lvfp_show_home';
		$all_ids     = get_posts(
			array(
				'post_type'      => LVFP_Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $all_ids as $post_id ) {
			if ( current_user_can( 'edit_post', $post_id ) ) {
				update_post_meta( $post_id, $meta_key, in_array( (int) $post_id, $visible_ids, true ) ? '1' : '0' );
			}
		}
	}

	/**
	 * Add useful columns to the programs list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function program_columns( $columns ) {
		$columns['lvfp_label'] = 'Рубрика';
		$columns['lvfp_home']  = 'Главная';
		$columns['lvfp_page']  = 'Страница программ';
		return $columns;
	}

	/**
	 * Render custom list table cells.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Program ID.
	 */
	public function program_column_content( $column, $post_id ) {
		if ( 'lvfp_label' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_lvfp_label', true ) );
			return;
		}

		if ( 'lvfp_home' === $column || 'lvfp_page' === $column ) {
			$key     = 'lvfp_home' === $column ? '_lvfp_show_home' : '_lvfp_show_page';
			$value   = get_post_meta( $post_id, $key, true );
			$visible = '' === $value || '1' === $value;
			echo $visible ? '<span class="lvfp-list-yes">Да</span>' : '<span class="lvfp-list-no">Нет</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
