<?php
/**
 * PLGC Two-Column Accordion Widget
 *
 * Renders a stack of accordion items matching the Figma "Accordion_2" component.
 *
 * Each accordion item has the same header structure:
 *   [Title — left] [Summary — flex fill] [Chevron — right]
 *
 * The EXPANDED PANEL supports two layout modes, chosen per item:
 *
 *  ① Simple (default)
 *     Two columns side-by-side:
 *       Left  — short description paragraph(s)
 *       Right — optional price/benefits heading + bullet list
 *     Use for: Ultimate Membership, Practice Membership, etc.
 *
 *  ② Sub-Items Grid
 *     Full-width intro text (optional) → full-width green section heading →
 *     2×2 responsive grid of sub-tier cards, each with their own
 *     price heading, description, and bullet list.
 *     Use for: Practice & Play Memberships (Individual / Family / Junior / Senior).
 *
 * WCAG 2.1 AA checklist:
 *  SC 4.1.2  — button[aria-expanded] + aria-controls on every trigger
 *  SC 2.1.1  — Enter/Space on native <button>; Tab into open panel content
 *  SC 2.4.4  — all visible trigger text (title + summary) inside the button
 *  SC 1.3.1  — heading tag (H2/H3/H4) wraps each trigger; configurable per widget
 *  SC 2.5.5  — full-width trigger; min-height 44px in CSS
 *  SC 2.4.7  — focus-visible ring: #567915 on white = 5.07:1
 *  SC 1.4.3  — #000 on #F2F2F2 = 18.1:1; #567915 on #F2F2F2 = 4.52:1
 *  SC 1.4.11 — chevron #233C26 on #F2F2F2 = 12:1
 *  SC 2.2.2  — no auto-advancing content
 *  prefers-reduced-motion — transitions disabled in CSS
 *
 * @package PLGC
 * @since   1.6.31
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class PLGC_Two_Col_Accordion_Widget extends Widget_Base {

	public function get_name(): string      { return 'plgc_two_col_accordion'; }
	public function get_title(): string     { return 'PLGC — Two-Column Accordion'; }
	public function get_icon(): string      { return 'eicon-accordion'; }
	public function get_categories(): array { return [ 'plgc' ]; }
	public function get_keywords(): array   {
		return [ 'accordion', 'membership', 'two column', 'rates', 'benefits', 'sub-items', 'plgc' ];
	}

	public function get_style_depends(): array  { return [ 'plgc-two-col-accordion' ]; }
	public function get_script_depends(): array { return [ 'plgc-two-col-accordion' ]; }

	// ---------------------------------------------------------------------------
	// Controls
	// ---------------------------------------------------------------------------

	protected function register_controls(): void {

		/* ── Accordion Items section ──────────────────────────────────────── */
		$this->start_controls_section( 'section_items', [
			'label' => 'Accordion Items',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'a11y_notice', [
			'type' => Controls_Manager::RAW_HTML,
			'raw'  => '<div style="background:#fff8e1;border-left:4px solid #FFAE40;padding:10px 14px;border-radius:0 4px 4px 0;font-size:12px;line-height:1.7;color:#333;">'
				. '<strong>&#9857; Accessibility tip:</strong> Accordion titles become heading buttons on the page. '
				. 'Make each title descriptive on its own — screen readers navigate headings as a list.</div>',
		] );

		/* ── Main repeater ───────────────────────────────────────────────── */
		$repeater = new Repeater();

		// Always-visible -------------------------------------------------------

		$repeater->add_control( 'title', [
			'label'       => 'Title',
			'type'        => Controls_Manager::TEXT,
			'default'     => 'Membership Title',
			'label_block' => true,
			'description' => 'Large heading on the left of the header bar.',
		] );

		$repeater->add_control( 'summary', [
			'label'       => 'Header Summary',
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => 'A brief overview shown in the collapsed header row.',
			'label_block' => true,
			'description' => 'Shown beside the title whether the item is open or closed.',
		] );

		$repeater->add_control( 'open_default', [
			'label'        => 'Open by default',
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => 'Yes',
			'label_off'    => 'No',
			'return_value' => 'yes',
			'default'      => '',
		] );

		// Panel layout selector ------------------------------------------------

		$repeater->add_control( 'panel_layout', [
			'label'       => 'Panel Layout',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'simple',
			'options'     => [
				'simple'    => 'Two columns — description left, benefits right',
				'sub-items' => 'Sub-items grid — shared intro + tier cards',
			],
			'description' => '<strong>Two columns:</strong> use for a single set of benefits (e.g. Ultimate Membership).<br>'
				. '<strong>Sub-items grid:</strong> use when one type has multiple tiers (e.g. Individual, Family, Junior, Senior).',
		] );

		// -- Simple layout fields (condition: panel_layout = simple) -----------

		$repeater->add_control( 'simple_label', [
			'type'      => Controls_Manager::RAW_HTML,
			'raw'       => '<hr style="margin:8px 0 4px;border-color:#ddd;"><strong style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#555;">Two-Column Content</strong>',
			'condition' => [ 'panel_layout' => 'simple' ],
		] );

		$repeater->add_control( 'col1_content', [
			'label'       => 'Left Column — Description',
			'type'        => Controls_Manager::WYSIWYG,
			'default'     => '<p>A short description of what this membership includes at a glance.</p>',
			'label_block' => true,
			'description' => 'Typically one short paragraph. Left side when expanded.',
			'condition'   => [ 'panel_layout' => 'simple' ],
		] );

		$repeater->add_control( 'price_heading', [
			'label'       => 'Right Column — Price / Heading',
			'type'        => Controls_Manager::TEXT,
			'default'     => '$4,800 - Benefits include:',
			'label_block' => true,
			'description' => 'Appears in green above the bullet list.',
			'condition'   => [ 'panel_layout' => 'simple' ],
		] );

		$repeater->add_control( 'col2_content', [
			'label'       => 'Right Column — Benefits List',
			'type'        => Controls_Manager::WYSIWYG,
			'default'     => '<ul><li>Benefit one</li><li>Benefit two</li><li>Benefit three</li></ul>',
			'label_block' => true,
			'description' => 'Use the list button in the toolbar to create a bullet list.',
			'condition'   => [ 'panel_layout' => 'simple' ],
		] );

		// -- Sub-items layout fields (condition: panel_layout = sub-items) -----

		$repeater->add_control( 'sub_label', [
			'type'      => Controls_Manager::RAW_HTML,
			'raw'       => '<hr style="margin:8px 0 4px;border-color:#ddd;"><strong style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#555;">Sub-Items Content</strong>',
			'condition' => [ 'panel_layout' => 'sub-items' ],
		] );

		$repeater->add_control( 'intro_text', [
			'label'       => 'Shared Benefits (optional)',
			'type'        => Controls_Manager::WYSIWYG,
			'default'     => '',
			'label_block' => true,
			'description' => 'Benefits that apply to ALL tiers — shown above the tier cards. Leave blank if none.',
			'condition'   => [ 'panel_layout' => 'sub-items' ],
		] );

		$repeater->add_control( 'section_heading', [
			'label'       => 'Section Heading',
			'type'        => Controls_Manager::TEXT,
			'default'     => 'We offer four membership options:',
			'label_block' => true,
			'description' => 'Full-width green heading above the tier grid.',
			'condition'   => [ 'panel_layout' => 'sub-items' ],
		] );

		// Nested sub-items repeater --------------------------------------------

		$sub_repeater = new Repeater();

		$sub_repeater->add_control( 'sub_price_heading', [
			'label'       => 'Price / Tier Name',
			'type'        => Controls_Manager::TEXT,
			'default'     => '$2,600 - Individual',
			'label_block' => true,
			'description' => 'Shown in green. E.g. "$2,600 - Individual"',
		] );

		$sub_repeater->add_control( 'sub_description', [
			'label'       => 'Description',
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => 'Who this tier is best suited for.',
			'label_block' => true,
		] );

		$sub_repeater->add_control( 'sub_benefits', [
			'label'       => 'Benefits List',
			'type'        => Controls_Manager::WYSIWYG,
			'default'     => '<ul><li>Benefit one</li><li>Benefit two</li></ul>',
			'label_block' => true,
			'description' => 'Use the list button in the toolbar. Leave blank if benefits are listed above.',
		] );

		$repeater->add_control( 'sub_items', [
			'label'       => 'Tier Cards',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $sub_repeater->get_controls(),
			'default'     => [
				[
					'sub_price_heading' => '$2,600 - Individual',
					'sub_description'   => 'For the individual wanting to practice all the time and play in the afternoon and evenings.',
					'sub_benefits'      => '<ul><li>Membership benefits available 7 days per week</li><li>Complimentary green fees after 2:00 PM</li><li>Golf cart fee additional</li><li>Daily range balls (up to two large buckets via key fob)</li><li>Unlimited use of practice holes/short game area</li><li>14-day advance tee time booking</li></ul>',
				],
				[
					'sub_price_heading' => '$3,500 - Family',
					'sub_description'   => 'Best for a family of four who like to play together in the afternoon and evenings.',
					'sub_benefits'      => '<ul><li>Members must be from a single household</li><li>Membership benefits available 7 days per week</li><li>Complimentary green fees for up to four members after 2:00 PM</li><li>Daily range balls (four large buckets via key fob each day)</li><li>Unlimited use of practice holes/short game area</li><li>14-day advance tee time booking</li></ul>',
				],
				[
					'sub_price_heading' => '$1,800 - Junior (18 and under)',
					'sub_description'   => 'For the student or young adult that wants to work on their game and play in the afternoon and evenings.',
					'sub_benefits'      => '<ul><li>Membership benefits available 7 days per week</li><li>Complimentary green fees after 2:00 PM</li><li>Golf cart fee additional (must be 16+ with valid license)</li><li>Daily range balls (two large buckets via key fob each day)</li><li>Unlimited use of practice holes/short game area</li><li>14-day advance tee time booking</li></ul>',
				],
				[
					'sub_price_heading' => '$2,800 - Senior (must be 60+)',
					'sub_description'   => 'For the retired individual who likes to practice and play first thing in the morning during the week.',
					'sub_benefits'      => '<ul><li>Complimentary green fees Monday-Thursday before 2:00 PM and Friday after 2:00 PM</li><li>Golf cart fee is additional</li><li>Does not include holiday weekday mornings</li><li>Daily range balls (two large buckets via key fob each day)</li><li>Unlimited use of practice holes/short game area</li><li>14-day advance tee time booking</li></ul>',
				],
			],
			'title_field' => '{{{ sub_price_heading }}}',
			'condition'   => [ 'panel_layout' => 'sub-items' ],
		] );

		/* ── Register the outer repeater ─────────────────────────────────── */
		$this->add_control( 'items', [
			'label'       => 'Items',
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => [
				[
					'title'         => 'Ultimate Membership',
					'summary'       => 'Our most popular membership is perfect for the individual looking for all the benefits of a practice membership plus the ability to play anytime on any day of the week. Green fees with a cart are no charge.',
					'open_default'  => '',
					'panel_layout'  => 'simple',
					'col1_content'  => '<p>Includes the use of our practice facilities plus unlimited green fees, unlimited cart fees, a personal locker, and club storage.</p>',
					'price_heading' => '$4,800 - Ultimate Membership benefits include:',
					'col2_content'  => '<ul><li>Unlimited green fees (cart included)</li><li>Daily range balls for the practice range (two large buckets)</li><li>Unlimited use of practice holes/short game area</li><li>14-day advance tee time booking</li><li>Locker and Club Storage</li><li>10% discount on food at McChesney\'s Pub &amp; Grill</li><li>3 reduced guest passes with cart each day</li><li>Guest passes are $70 Monday - Thursday or $90 on Friday, Saturday, Sunday, and Holidays (member must be present)</li></ul>',
				],
				[
					'title'           => 'Practice & Play Memberships',
					'summary'         => 'Great for the individual, family, senior, or junior to enjoy an inclusive price for driving range, practice holes, and short game area. Includes limited green fees along with all the additional member benefits.',
					'open_default'    => '',
					'panel_layout'    => 'sub-items',
					'intro_text'      => '<p>$1,000 per person - Unlimited Cart Fees (per person)</p><p>5% discount on food at McChesney\'s Pub &amp; Grill</p>',
					'section_heading' => 'We offer four Practice & Play Membership options:',
				],
			],
			'title_field' => '{{{ title }}}',
		] );

		$this->end_controls_section();

		/* ── Settings section ────────────────────────────────────────────── */
		$this->start_controls_section( 'section_settings', [
			'label' => 'Settings',
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'heading_tag', [
			'label'       => 'Heading Tag for Titles',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'h2',
			'options'     => [
				'h2' => 'H2',
				'h3' => 'H3',
				'h4' => 'H4',
			],
			'description' => 'Match to page heading hierarchy. If this accordion sits below the page H1, use H2.',
		] );

		$this->add_control( 'allow_multiple', [
			'label'        => 'Allow multiple items open at once',
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => 'Yes',
			'label_off'    => 'No',
			'return_value' => 'yes',
			'default'      => 'yes',
			'description'  => 'When off, opening one item closes any currently open item.',
		] );

		$this->end_controls_section();
	}

	// ---------------------------------------------------------------------------
	// Render
	// ---------------------------------------------------------------------------

	protected function render(): void {
		$settings       = $this->get_settings_for_display();
		$items          = $settings['items'] ?? [];
		$heading_tag    = in_array( $settings['heading_tag'], [ 'h2', 'h3', 'h4' ], true )
			? $settings['heading_tag'] : 'h2';
		$allow_multiple = ( $settings['allow_multiple'] ?? 'yes' ) === 'yes';
		$widget_id      = $this->get_id();

		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="plgc-two-col-accordion"
		     data-allow-multiple="<?php echo $allow_multiple ? 'true' : 'false'; ?>">

			<?php foreach ( $items as $index => $item ) :

				$item_id    = 'plgc-acc-' . esc_attr( $widget_id ) . '-' . $index;
				$panel_id   = $item_id . '-panel';
				$trigger_id = $item_id . '-trigger';
				$is_open    = ! empty( $item['open_default'] );
				$layout     = ( isset( $item['panel_layout'] ) && $item['panel_layout'] === 'sub-items' )
					? 'sub-items' : 'simple';

				$title   = wp_kses_post( $item['title']   ?? '' );
				$summary = wp_kses_post( $item['summary'] ?? '' );

			?>
			<div class="plgc-accordion-item<?php echo $is_open ? ' is-open' : ''; ?>">

				<<?php echo esc_attr( $heading_tag ); ?> class="plgc-accordion-header">
					<button
						class="plgc-accordion-trigger"
						id="<?php echo esc_attr( $trigger_id ); ?>"
						aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
						aria-controls="<?php echo esc_attr( $panel_id ); ?>">

						<span class="plgc-accordion-title"><?php echo $title; ?></span>
						<span class="plgc-accordion-summary"><?php echo $summary; ?></span>

						<span class="plgc-accordion-icon" aria-hidden="true">
							<svg width="21" height="12" viewBox="0 0 21 12" fill="none"
							     xmlns="http://www.w3.org/2000/svg" focusable="false">
								<path d="M1 1L10.5 11L20 1"
								      stroke="#233C26"
								      stroke-width="2"
								      stroke-linecap="round"
								      stroke-linejoin="round"/>
							</svg>
						</span>

					</button>
				</<?php echo esc_attr( $heading_tag ); ?>>

				<div
					id="<?php echo esc_attr( $panel_id ); ?>"
					class="plgc-accordion-panel"
					role="region"
					aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
					<?php if ( ! $is_open ) echo 'hidden'; ?>>

					<?php if ( $layout === 'simple' ) : ?>
						<?php $this->render_simple_panel( $item ); ?>
					<?php else : ?>
						<?php $this->render_sub_items_panel( $item ); ?>
					<?php endif; ?>

				</div>

			</div>
			<?php endforeach; ?>

		</div>
		<?php
	}

	// ---------------------------------------------------------------------------
	// Panel render helpers
	// ---------------------------------------------------------------------------

	/**
	 * Two-column panel: description left, price heading + bullets right.
	 * Matches Figma Accordion_2 — Ultimate Membership variant.
	 */
	private function render_simple_panel( array $item ): void {
		$col1          = wp_kses_post( $item['col1_content'] ?? '' );
		$price_heading = wp_kses( $item['price_heading'] ?? '', [ 'em' => [], 'strong' => [], 'br' => [] ] );
		$col2          = wp_kses_post( $item['col2_content'] ?? '' );
		?>
		<div class="plgc-accordion-content plgc-accordion-content--simple">
			<div class="plgc-accordion-col1">
				<?php echo $col1; ?>
			</div>
			<div class="plgc-accordion-col2">
				<?php if ( ! empty( $price_heading ) ) : ?>
					<p class="plgc-accordion-price-heading"><?php echo $price_heading; ?></p>
				<?php endif; ?>
				<?php echo $col2; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Sub-items panel: optional intro → optional section heading → 2-col tier grid.
	 * Matches Figma Accordion_2 — Practice & Play variant.
	 */
	private function render_sub_items_panel( array $item ): void {
		$intro_text      = wp_kses_post( $item['intro_text']      ?? '' );
		$section_heading = wp_kses( $item['section_heading'] ?? '', [ 'em' => [], 'strong' => [] ] );
		$sub_items       = $item['sub_items'] ?? [];
		?>
		<div class="plgc-accordion-content plgc-accordion-content--sub">

			<?php if ( ! empty( $intro_text ) ) : ?>
				<div class="plgc-accordion-intro">
					<?php echo $intro_text; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $section_heading ) ) : ?>
				<p class="plgc-accordion-section-heading"><?php echo $section_heading; ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $sub_items ) ) : ?>
				<div class="plgc-accordion-sub-grid">
					<?php foreach ( $sub_items as $sub ) :
						$sub_heading = wp_kses( $sub['sub_price_heading'] ?? '', [ 'em' => [], 'strong' => [] ] );
						$sub_desc    = wp_kses_post( $sub['sub_description'] ?? '' );
						$sub_ben     = wp_kses_post( $sub['sub_benefits']    ?? '' );
					?>
					<div class="plgc-accordion-sub-item">

						<?php if ( ! empty( $sub_heading ) ) : ?>
							<p class="plgc-accordion-sub-heading"><?php echo $sub_heading; ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $sub_desc ) ) : ?>
							<p class="plgc-accordion-sub-desc"><?php echo $sub_desc; ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $sub_ben ) ) : ?>
							<div class="plgc-accordion-sub-benefits">
								<?php echo $sub_ben; ?>
							</div>
						<?php endif; ?>

					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}
}
