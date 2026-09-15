<?php
/**
 * Core plugin functionality.
 *
 * @package LVPrograms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LVFP_Plugin {
	const POST_TYPE     = 'lv_program';
	const OPTION_HEADERS = 'lvfp_headers';
	const OPTION_HOME    = 'lvfp_order_home';
	const OPTION_PAGE    = 'lvfp_order_page';

	/** @var LVFP_Plugin|null */
	private static $instance = null;

	/** @var int */
	private static $render_count = 0;

	/**
	 * Get singleton instance.
	 *
	 * @return LVFP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set up hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
	}

	/**
	 * Register the program post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => 'Программы фонда',
			'singular_name'      => 'Программа',
			'menu_name'          => 'Программы фонда',
			'add_new'            => 'Добавить программу',
			'add_new_item'       => 'Добавить программу',
			'edit_item'          => 'Редактировать программу',
			'new_item'           => 'Новая программа',
			'view_item'          => 'Просмотреть программу',
			'search_items'       => 'Найти программы',
			'not_found'          => 'Программы не найдены',
			'not_found_in_trash' => 'В корзине программ нет',
			'all_items'          => 'Все программы',
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-heart',
				'menu_position'      => 26,
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
				'query_var'          => false,
				'map_meta_cap'       => true,
				'capability_type'    => 'post',
			)
		);
	}

	/**
	 * Register public shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'lv_programs_home', array( $this, 'shortcode_home' ) );
		add_shortcode( 'lv_programs_page', array( $this, 'shortcode_page' ) );
	}

	/**
	 * Register public assets. They are enqueued only when a shortcode renders.
	 */
	public function register_front_assets() {
		wp_register_style(
			'lvfp-frontend',
			LVFP_URL . 'assets/css/frontend.css',
			array(),
			LVFP_VERSION
		);
		wp_register_script(
			'lvfp-frontend',
			LVFP_URL . 'assets/js/frontend.js',
			array(),
			LVFP_VERSION,
			true
		);
	}

	/**
	 * Render home shortcode.
	 *
	 * @return string
	 */
	public function shortcode_home() {
		return $this->render( 'home' );
	}

	/**
	 * Render programs page shortcode.
	 *
	 * @return string
	 */
	public function shortcode_page() {
		return $this->render( 'page' );
	}

	/**
	 * Render one shortcode context.
	 *
	 * @param string $context home|page.
	 * @return string
	 */
	private function render( $context ) {
		$context  = 'page' === $context ? 'page' : 'home';
		$programs = $this->get_ordered_programs( $context, array( 'publish' ) );

		if ( empty( $programs ) ) {
			return '';
		}

		wp_enqueue_style( 'lvfp-frontend' );
		wp_enqueue_script( 'lvfp-frontend' );

		++self::$render_count;
		$instance_id = 'lvfp-' . $context . '-' . self::$render_count;
		$header      = $this->get_header( $context );
		$has_title   = ! empty( $header['title_visible'] ) && '' !== $header['title_text'];
		$label_attr  = $has_title
			? 'aria-labelledby="' . esc_attr( $instance_id . '-title' ) . '"'
			: 'aria-label="' . esc_attr__( 'Программы фонда', 'lv-programs' ) . '"';

		ob_start();
		?>
		<div class="lvfp lvfp--<?php echo esc_attr( $context ); ?>" id="<?php echo esc_attr( $instance_id ); ?>">
			<section class="lvfp__section" <?php echo $label_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php $this->render_header( $header, $instance_id ); ?>
				<div class="lvfp__grid">
					<?php foreach ( $programs as $index => $program ) : ?>
						<?php $this->render_card( $program, (int) $index ); ?>
					<?php endforeach; ?>
				</div>
				<?php if ( 'home' === $context ) : ?>
					<?php $this->render_home_button( $header ); ?>
				<?php endif; ?>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Output configured section header.
	 *
	 * @param array  $header Header configuration.
	 * @param string $instance_id Unique wrapper id.
	 */
	private function render_header( $header, $instance_id ) {
		$has_header = ! empty( $header['eyebrow_visible'] )
			|| ! empty( $header['title_visible'] )
			|| ! empty( $header['lead_visible'] );

		if ( ! $has_header ) {
			return;
		}
		?>
		<header class="lvfp__head">
			<?php if ( ! empty( $header['eyebrow_visible'] ) && '' !== $header['eyebrow_text'] ) : ?>
				<p class="lvfp__eyebrow"><?php echo esc_html( $header['eyebrow_text'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $header['title_visible'] ) && '' !== $header['title_text'] ) : ?>
				<h2 class="lvfp__title" id="<?php echo esc_attr( $instance_id . '-title' ); ?>">
					<?php echo esc_html( $header['title_text'] ); ?>
				</h2>
			<?php endif; ?>

			<?php if ( ! empty( $header['lead_visible'] ) && '' !== $header['lead_text'] ) : ?>
				<p class="lvfp__lead"><?php echo nl2br( esc_html( $header['lead_text'] ) ); ?></p>
			<?php endif; ?>
		</header>
		<?php
	}

	/**
	 * Output the homepage widget link when enabled and configured.
	 *
	 * @param array $header Homepage header configuration.
	 */
	private function render_home_button( $header ) {
		if ( empty( $header['button_visible'] ) || empty( $header['button_text'] ) || empty( $header['button_url'] ) ) {
			return;
		}
		?>
		<div class="lvfp__footer">
			<span class="lvfp__button-wrap">
				<span class="lvfp__button-echo" aria-hidden="true"></span>
				<a class="lvfp__button" href="<?php echo esc_url( $header['button_url'] ); ?>">
					<?php echo esc_html( $header['button_text'] ); ?>
				</a>
			</span>
		</div>
		<?php
	}

	/**
	 * Output one program card.
	 *
	 * @param WP_Post $program Program post.
	 * @param int     $index Display index.
	 */
	private function render_card( $program, $index ) {
		$accent = '';
		if ( 0 === $index % 6 ) {
			$accent = ' lvfp-card--dark';
		} elseif ( 3 === $index % 6 ) {
			$accent = ' lvfp-card--mint';
		}

		$label       = (string) get_post_meta( $program->ID, '_lvfp_label', true );
		$description = metadata_exists( 'post', $program->ID, '_lvfp_description' )
			? (string) get_post_meta( $program->ID, '_lvfp_description', true )
			: $program->post_content;
		$content     = wpautop( wp_kses_post( $description ) );
		?>
		<article class="lvfp-card<?php echo esc_attr( $accent ); ?>">
			<div class="lvfp-card__media">
				<?php echo $this->get_program_image( $program, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="lvfp-card__body">
				<?php if ( '' !== $label ) : ?>
					<span class="lvfp-card__label"><?php echo esc_html( $label ); ?></span>
				<?php endif; ?>
				<h3 class="lvfp-card__title"><?php echo esc_html( get_the_title( $program ) ); ?></h3>
				<div class="lvfp-card__desc"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
		</article>
		<?php
	}

	/**
	 * Return featured image, seeded fallback or decorative placeholder.
	 *
	 * @param WP_Post $program Program post.
	 * @param int     $index Display index.
	 * @return string
	 */
	private function get_program_image( $program, $index ) {
		$loading = 0 === $index ? 'eager' : 'lazy';
		$image_id = absint( get_post_meta( $program->ID, '_lvfp_image_id', true ) );

		if ( $image_id ) {
			$image = wp_get_attachment_image(
				$image_id,
				'large',
				false,
				array(
					'class'    => 'lvfp-card__img',
					'loading'  => $loading,
					'decoding' => 'async',
				)
			);

			if ( $image ) {
				return (string) $image;
			}
		}

		if ( has_post_thumbnail( $program ) ) {
			return (string) get_the_post_thumbnail(
				$program,
				'large',
				array(
					'class'    => 'lvfp-card__img',
					'loading'  => $loading,
					'decoding' => 'async',
				)
			);
		}

		$fallback = (string) get_post_meta( $program->ID, '_lvfp_fallback_image', true );
		if ( '' !== $fallback ) {
			return sprintf(
				'<img class="lvfp-card__img" src="%1$s" alt="%2$s" loading="%3$s" decoding="async">',
				esc_url( $fallback ),
				esc_attr( sprintf( 'Программа «%s»', get_the_title( $program ) ) ),
				esc_attr( $loading )
			);
		}

		return '<span class="lvfp-card__placeholder" aria-hidden="true"><span>ЛВ</span></span>';
	}

	/**
	 * Get normalized header settings for a context.
	 *
	 * @param string $context home|page.
	 * @return array
	 */
	public function get_header( $context ) {
		$all      = get_option( self::OPTION_HEADERS, array() );
		$defaults = self::default_headers();
		$current  = isset( $all[ $context ] ) && is_array( $all[ $context ] ) ? $all[ $context ] : array();

		return wp_parse_args( $current, $defaults[ $context ] );
	}

	/**
	 * Default headings for both placements.
	 *
	 * @return array
	 */
	public static function default_headers() {
		return array(
			'home' => array(
				'eyebrow_visible' => 1,
				'eyebrow_text'    => 'Помощь на практике',
				'title_visible'   => 1,
				'title_text'      => 'Программы фонда',
				'lead_visible'    => 1,
				'lead_text'       => 'От ежедневного ухода и реабилитации до создания безопасной среды, просвещения и защиты интересов животных.',
				'button_visible'  => 1,
				'button_text'     => 'Все программы',
				'button_url'      => '/programs/',
			),
			'page' => array(
				'eyebrow_visible' => 1,
				'eyebrow_text'    => 'Люди и Верблюды',
				'title_visible'   => 1,
				'title_text'      => 'Программы фонда',
				'lead_visible'    => 1,
				'lead_text'       => 'Системная помощь верблюдам — от ежедневного ухода до долгосрочной защиты и создания достойных условий жизни.',
			),
		);
	}

	/**
	 * Get program posts in the saved order for one context.
	 *
	 * @param string       $context home|page.
	 * @param array|string $statuses Post statuses.
	 * @return WP_Post[]
	 */
	public function get_ordered_programs( $context, $statuses = array( 'publish', 'draft', 'pending', 'private' ) ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
				'order'          => 'ASC',
			)
		);

		$by_id = array();
		foreach ( $posts as $post ) {
			$by_id[ $post->ID ] = $post;
		}

		$option_name = 'page' === $context ? self::OPTION_PAGE : self::OPTION_HOME;
		$saved_order = array_map( 'absint', (array) get_option( $option_name, array() ) );
		$result      = array();

		foreach ( $saved_order as $post_id ) {
			if ( isset( $by_id[ $post_id ] ) ) {
				$result[] = $by_id[ $post_id ];
				unset( $by_id[ $post_id ] );
			}
		}

		foreach ( $by_id as $post ) {
			$result[] = $post;
		}

		$visibility_key = 'page' === $context ? '_lvfp_show_page' : '_lvfp_show_home';

		return array_values(
			array_filter(
				$result,
				static function ( $post ) use ( $visibility_key ) {
					$value = get_post_meta( $post->ID, $visibility_key, true );
					return '' === $value || '1' === $value;
				}
			)
		);
	}

	/**
	 * Seed initial content on first activation.
	 */
	public static function activate() {
		$plugin = self::instance();
		$plugin->register_post_type();

		if ( false === get_option( self::OPTION_HEADERS, false ) ) {
			add_option( self::OPTION_HEADERS, self::default_headers(), '', false );
		}

		$existing = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $existing ) ) {
			$ids = array();
			foreach ( self::seed_programs() as $index => $item ) {
				$post_id = wp_insert_post(
					array(
						'post_type'    => self::POST_TYPE,
						'post_status'  => 'publish',
						'post_title'   => $item['title'],
						'post_content' => $item['description'],
						'menu_order'   => $index,
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				update_post_meta( $post_id, '_lvfp_label', $item['label'] );
				update_post_meta( $post_id, '_lvfp_description', $item['description'] );
				update_post_meta( $post_id, '_lvfp_show_home', '1' );
				update_post_meta( $post_id, '_lvfp_show_page', '1' );
				update_post_meta( $post_id, '_lvfp_fallback_image', $item['image'] );
				$ids[] = (int) $post_id;
			}

			update_option( self::OPTION_HOME, $ids, false );
			update_option( self::OPTION_PAGE, $ids, false );
		}

		flush_rewrite_rules();
	}

	/**
	 * Initial program content.
	 *
	 * @return array
	 */
	private static function seed_programs() {
		$images = array(
			'https://bfcamel.ru/wp-content/uploads/2026/08/прог1.webp',
			'https://bfcamel.ru/wp-content/uploads/2026/08/прог2.webp',
			'https://bfcamel.ru/wp-content/uploads/2026/08/прог3.webp',
			'https://bfcamel.ru/wp-content/uploads/2026/08/прог4.webp',
		);

		return array(
			array(
				'label' => 'Добровольцы',
				'title' => 'Волонтёрская деятельность',
				'description' => 'Привлечение, отбор, сопровождение и мотивация добровольцев для помощи животным. Программа обеспечивает систему инструктажей, учёта времени, безопасных условий работы и поощрений, позволяя участникам выбирать удобный формат участия — от ухода за верблюдами до организационной и информационной поддержки.',
				'image' => $images[0],
			),
			array(
				'label' => 'Питание',
				'title' => 'Накорми подопечного',
				'description' => 'Организация бесперебойного снабжения кормами верблюдов, находящихся на попечении фонда. В рамках программы формируются рационы с учётом возраста, здоровья и индивидуальных особенностей, закупаются сено, зерно, специализированные смеси и витаминно-минеральные комплексы, обеспечивается их хранение и доставка.',
				'image' => $images[1],
			),
			array(
				'label' => 'Зоопарки',
				'title' => 'Комфортная жизнь в зоопарке',
				'description' => 'Целевая помощь верблюдам, содержащимся в государственных и частных зоопарках. Программа покрывает закупку кормов, витаминных добавок, ветеринарные осмотры и лечение, а также модернизацию вольеров, укрытий и мест водопоя для создания условий, соответствующих природным потребностям животных.',
				'image' => $images[2],
			),
			array(
				'label' => 'Логистика',
				'title' => 'Перевозка крупных больных животных',
				'description' => 'Создание условий для гуманной и безопасной транспортировки крупных животных — прежде всего ослабленных, больных или нуждающихся в особом режиме. Программа предусматривает приобретение и адаптацию специального транспорта, оплату логистики и ветеринарное сопровождение, чтобы животные могли быть доставлены к месту лечения или в более подходящие условия содержания.',
				'image' => $images[3],
			),
			array(
				'label' => 'Реабилитация',
				'title' => 'Психологическая социализация и реабилитация',
				'description' => 'Программа помогает верблюдам, пережившим стресс и жестокое обращение, восстановить доверие к человеку, перестать бояться громких звуков, прикосновений, осмотров, прививок и лечебных процедур. Пожертвования направляются на услуги опытных специалистов, в том числе из системы Росгосцирка, их проезд, питание и проживание, а также на обследования, лечение и создание безопасных условий для реабилитации животных.',
				'image' => $images[0],
			),
			array(
				'label' => 'Защита животных',
				'title' => 'Ответственное верблюдоводство',
				'description' => 'Осуществление деятельности в сфере защиты животных, прежде всего верблюдов и иных животных семейства верблюдовых, а также иных домашних, сельскохозяйственных, содержащихся в неволе, безнадзорных, брошенных, пострадавших, больных, старых, травмированных, конфискованных, изъятых, спасённых и иных животных, нуждающихся в помощи; участие в мероприятиях по охране окружающей среды, сохранению природных экосистем, мест обитания животных и биологического разнообразия.',
				'image' => $images[1],
			),
			array(
				'label' => 'Пожизненная помощь',
				'title' => 'Дом престарелых для верблюдов',
				'description' => 'Организация содержания, пожизненного содержания, кормления, ухода, размещения, временного содержания, ветеринарной помощи, лечения, профилактики заболеваний, реабилитации, адаптации и паллиативного ухода за животными, а также обеспечение иных необходимых условий их жизни и здоровья.',
				'image' => $images[2],
			),
			array(
				'label' => 'Инфраструктура',
				'title' => 'Последняя гавань корабля пустыни',
				'description' => 'Создание, содержание, развитие и обеспечение деятельности приютов, центров помощи, центров временного содержания, реабилитационных центров, центров пожизненного содержания, ветеринарных блоков, пастбищ, вольеров, стойл и иных объектов, необходимых для содержания и помощи животным.',
				'image' => $images[3],
			),
			array(
				'label' => 'Просвещение',
				'title' => 'Просвещение, образование и наука',
				'description' => 'Проведение просветительской, научной, образовательной, информационной и методической деятельности в сфере защиты животных; проведение лекций, семинаров, конференций, круглых столов, выставок, экскурсий, благотворительных, культурных и иных мероприятий; создание и ведение сайтов, страниц в социальных сетях, каналов, фото-, аудио- и видеоматериалов, печатных и электронных изданий; производство и распространение социальной рекламы.',
				'image' => $images[0],
			),
			array(
				'label' => 'Правовая защита',
				'title' => 'Общественный контроль',
				'description' => 'Участие в общественном контроле в сфере обращения с животными в случаях и порядке, предусмотренных законодательством Российской Федерации; рассмотрение обращений, направление заявлений, жалоб и запросов в компетентные органы; оказание информационной, консультационной и правовой помощи по вопросам защиты животных; представление и защита прав и законных интересов Фонда, а также общественных интересов в сфере защиты животных в случаях и порядке, предусмотренных законодательством Российской Федерации.',
				'image' => $images[1],
			),
		);
	}
}
