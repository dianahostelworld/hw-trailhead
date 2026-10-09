<?php
/**
 * The Trailhead screens.
 *
 * @package HW_Trailhead
 */

defined( 'ABSPATH' ) || exit;

/**
 * One admin page with a side menu, like Content Health.
 */
class TH_Admin {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page( __( 'Trailhead', 'hw-trailhead' ), __( 'Trailhead', 'hw-trailhead' ), TH_Actions::CAP, 'trailhead', array( __CLASS__, 'render' ), 'dashicons-location-alt', 4 );
	}

	/**
	 * Styles.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_trailhead' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'th-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Poppins:wght@700;800&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'th-admin', TH_URL . 'assets/admin.css', array( 'th-fonts' ), TH_VERSION );
	}

	/**
	 * A link to a view.
	 *
	 * @param string $view View.
	 * @param array  $args More args.
	 * @return string
	 */
	public static function url( $view, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'trailhead', 'view' => $view ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Number format.
	 *
	 * @param mixed $n Number.
	 * @return string
	 */
	private static function n( $n ) {
		return null === $n || '' === $n ? '–' : number_format_i18n( (float) $n );
	}

	/**
	 * Short date.
	 *
	 * @param string $d Y-m-d.
	 * @return string
	 */
	private static function d( $d ) {
		return $d ? date_i18n( 'j M', strtotime( $d . ' 12:00:00' ) ) : '–';
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public static function render() {
		$applied = TH_Tasks::collect();
		$view    = sanitize_key( $_GET['view'] ?? 'today' ); // phpcs:ignore WordPress.Security.NonceVerification
		$views   = array( 'today', 'research', 'ideas', 'start', 'briefs', 'drafts', 'refresh', 'refresh_item', 'queue', 'settings' );
		$view    = in_array( $view, $views, true ) ? $view : 'today';

		$rows    = TH_Schedule::rows();
		$today   = TH_Schedule::today();
		$due     = count( array_filter( $rows, function ( $r ) use ( $today ) { return $r['interval'] && 'none' !== $r['interval'] && $r['due'] && $r['due'] <= $today; } ) );
		$waiting = count( TH_Tasks::waiting() );
		$counts  = array(
			'ideas'   => TH_Items::count( array( 'status' => 'new' ) ),
			'briefs'  => TH_Items::count( array( 'status' => 'approved', 'stage' => 'brief' ) ),
			'drafts'  => TH_Items::count( array( 'status' => 'approved', 'stage' => 'draft' ) ),
			'refresh' => $due,
			'queue'   => $waiting,
		);
		$msg = get_transient( 'th_msg_' . get_current_user_id() );
		delete_transient( 'th_msg_' . get_current_user_id() );

		$nav = array(
			__( 'Create', 'hw-trailhead' )   => array( 'today' => __( 'Today', 'hw-trailhead' ), 'research' => __( 'Research', 'hw-trailhead' ), 'start' => __( 'New article', 'hw-trailhead' ), 'ideas' => __( 'Ideas', 'hw-trailhead' ), 'briefs' => __( 'Briefs', 'hw-trailhead' ), 'drafts' => __( 'Drafts', 'hw-trailhead' ) ),
			__( 'Maintain', 'hw-trailhead' ) => array( 'refresh' => __( 'Refresh', 'hw-trailhead' ), 'queue' => __( 'Claude queue', 'hw-trailhead' ), 'settings' => __( 'Settings', 'hw-trailhead' ) ),
		);
		$current = 'refresh_item' === $view ? 'refresh' : $view;
		?>
		<div class="wrap th-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Trailhead', 'hw-trailhead' ); ?></h1>
			<div class="th-shell">
				<nav class="th-side" aria-label="<?php esc_attr_e( 'Trailhead sections', 'hw-trailhead' ); ?>">
					<div class="th-brand">
						<svg width="30" height="30" viewBox="0 0 164 164" aria-hidden="true"><path d="M164 141.173C164 153.816 153.816 164 141.173 164H22.8266C10.1842 164 0 153.816 0 141.173V22.8266C0 10.1842 10.1842 0 22.8266 0H141.173C153.816 0 164 10.1842 164 22.8266V141.173Z" fill="#F25621"/><path d="M135.731 26.1628H102.895C101.666 26.1628 100.613 27.2163 100.613 28.4454V57.7688H104.3C104.827 57.7688 105.178 58.2955 105.178 58.8223C105.178 58.9979 105.178 59.1735 105.002 59.3491L84.1074 80.5953C83.4051 81.4733 83.4051 82.5268 84.1074 83.4047L105.178 104.827C105.354 105.002 105.354 105.178 105.354 105.353C105.354 105.88 105.002 106.231 104.476 106.407H100.788V135.73C100.788 136.959 101.842 138.013 103.071 138.013H135.906C137.135 138.013 138.189 136.959 138.189 135.73V28.2698C137.838 27.0407 136.96 26.1628 135.731 26.1628ZM59.1738 59.3491C58.8226 58.9979 58.8226 58.2955 59.1738 57.9444C59.3494 57.7688 59.525 57.5932 59.8761 57.5932H63.5635V28.2698C63.5635 27.0407 62.51 25.9872 61.2809 25.9872H28.4457C27.2166 25.9872 26.1631 27.0407 26.1631 28.2698V135.555C26.1631 136.784 27.2166 137.837 28.4457 137.837H61.2809C62.51 137.837 63.5635 136.784 63.5635 135.555V106.407H59.8761C59.3494 106.407 58.8226 105.88 58.9982 105.353C58.9982 105.178 59.1738 104.827 59.3494 104.651L80.2445 83.4047C80.9468 82.5268 80.9468 81.4733 80.2445 80.5953L59.1738 59.3491Z" fill="#fff"/></svg>
						<div><b>Trailhead</b><span><?php esc_html_e( 'Content pipeline', 'hw-trailhead' ); ?></span></div>
					</div>
					<?php foreach ( $nav as $group => $items ) : ?>
						<span class="th-grp"><?php echo esc_html( $group ); ?></span>
						<?php foreach ( $items as $k => $label ) : ?>
							<a class="th-nav" href="<?php echo esc_url( self::url( $k ) ); ?>"<?php echo $current === $k ? ' aria-current="page"' : ''; ?>><span><?php echo esc_html( $label ); ?></span><?php if ( ! empty( $counts[ $k ] ) ) : ?><span class="th-cnt"><?php echo esc_html( number_format_i18n( $counts[ $k ] ) ); ?></span><?php endif; ?></a>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</nav>
				<main class="th-main">
					<?php if ( ! TH_Data::ready() ) : ?>
						<div class="th-notice bad"><?php esc_html_e( 'Trailhead reads Search Console data from Content Health. Install Content Health and run its first sync to see clicks, impressions and opportunities.', 'hw-trailhead' ); ?></div>
					<?php endif; ?>
					<?php if ( $msg ) : ?><div class="th-notice ok"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
					<?php if ( $applied ) : ?><div class="th-notice ok"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( 'Claude finished %d task since you last looked. It is in place below.', 'Claude finished %d tasks since you last looked. They are in place below.', $applied, 'hw-trailhead' ), $applied ) ); ?></div><?php endif; ?>
					<?php if ( $waiting && 'queue' !== $view ) : ?>
						<div class="th-notice"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d task is waiting for Claude.', '%d tasks are waiting for Claude.', $waiting, 'hw-trailhead' ), $waiting ) ); ?> <?php esc_html_e( 'They are done at the next run of your Trailhead task in Claude.', 'hw-trailhead' ); ?> <a href="<?php echo esc_url( self::url( 'queue' ) ); ?>"><?php esc_html_e( 'See the queue', 'hw-trailhead' ); ?></a></div>
					<?php endif; ?>
					<?php call_user_func( array( __CLASS__, 'view_' . $view ) ); ?>
				</main>
			</div>
		</div>
		<script>
		document.addEventListener( 'change', function ( e ) { if ( e.target.matches( '[data-autosubmit]' ) ) { e.target.form.submit(); } } );
		</script>
		<?php
	}

	/**
	 * Page heading.
	 *
	 * @param string $eyebrow Eyebrow.
	 * @param string $title   Title.
	 * @param string $lede    Lede.
	 * @param string $right   HTML on the right.
	 * @return void
	 */
	private static function head( $eyebrow, $title, $lede = '', $right = '' ) {
		echo '<div class="th-head"><div><p class="th-eb">' . esc_html( $eyebrow ) . '</p><h2 class="th-h1">' . esc_html( $title ) . '</h2>';
		if ( $lede ) {
			echo '<p class="th-lede">' . esc_html( $lede ) . '</p>';
		}
		echo '</div>' . $right . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * A small form with one button.
	 *
	 * @param string $do     Action.
	 * @param string $label  Label.
	 * @param array  $fields Hidden fields.
	 * @param string $class  Button class.
	 * @return string
	 */
	private static function button( $do, $label, $fields = array(), $class = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="th-inline">' . TH_Actions::fields( $do, $fields ) . '<button class="th-btn ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Chip for a waiting task.
	 *
	 * @param array|null $w From TH_Tasks::waiting_for().
	 * @return string
	 */
	private static function waiting_chip( $w ) {
		if ( ! $w ) {
			return '';
		}
		return '<span class="th-chip warn">' . esc_html( sprintf( /* translators: %s: time ago */ __( 'Waiting for Claude · queued %s ago', 'hw-trailhead' ), human_time_diff( strtotime( $w['since'] . ' UTC' ) ) ) ) . '</span>';
	}

	/* ---------------- Today ---------------- */

	/**
	 * Today.
	 *
	 * @return void
	 */
	private static function view_today() {
		$rows  = TH_Schedule::rows();
		$today = TH_Schedule::today();
		$due   = array_filter( $rows, function ( $r ) use ( $today ) { return $r['interval'] && 'none' !== $r['interval'] && $r['due'] && $r['due'] <= $today; } );
		uasort( $due, function ( $a, $b ) { return strcmp( $a['due'], $b['due'] ) ?: $b['recent'] - $a['recent']; } );
		$types = TH_Schedule::types();
		self::head( date_i18n( 'l j F' ), __( 'Today on the blog', 'hw-trailhead' ), __( 'What needs you: refreshes that are due, ideas to judge and drafts to review. Nothing is published unless you publish it.', 'hw-trailhead' ), '<a class="th-btn p" href="' . esc_url( self::url( 'research' ) ) . '">' . esc_html__( 'Find opportunities', 'hw-trailhead' ) . '</a>' );
		?>
		<section class="th-card hot">
			<div class="th-row between"><h3><?php esc_html_e( 'Refreshes due today', 'hw-trailhead' ); ?></h3><?php if ( $due ) : ?><span class="th-chip warn"><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s due', 'hw-trailhead' ), number_format_i18n( count( $due ) ) ) ); ?></span><?php endif; ?></div>
			<?php if ( ! get_option( 'th_schedule_setup' ) ) : ?>
				<div class="th-empty"><b><?php esc_html_e( 'No schedule yet.', 'hw-trailhead' ); ?></b><span><?php esc_html_e( 'Set it up once: the most valuable articles are refreshed every quarter, the rest with clicks every year, and articles with no clicks wait for a prune decision.', 'hw-trailhead' ); ?></span><?php echo self::button( 'schedule_setup', __( 'Set up the schedule', 'hw-trailhead' ), array(), 'p' ); // phpcs:ignore ?></div>
			<?php elseif ( ! $due ) : ?>
				<p class="th-muted"><?php esc_html_e( 'Nothing due today.', 'hw-trailhead' ); ?></p>
			<?php else : ?>
				<div class="th-list">
					<?php foreach ( array_slice( $due, 0, 8, true ) as $id => $r ) : ?>
						<div class="th-li">
							<span><b><?php echo esc_html( get_the_title( $id ) ); ?></b><br><span class="th-chip <?php echo esc_attr( $types[ $r['type'] ][1] ); ?>"><?php echo esc_html( $types[ $r['type'] ][0] ); ?></span> <span class="th-muted th-small"><?php echo esc_html( $r['due'] < $today ? sprintf( /* translators: %d: days */ __( '%d days overdue', 'hw-trailhead' ), ( strtotime( $today ) - strtotime( $r['due'] ) ) / DAY_IN_SECONDS ) : __( 'due today', 'hw-trailhead' ) ); ?><?php echo $r['note'] ? ' · ' . esc_html( $r['note'] ) : ''; ?></span></span>
							<a class="th-btn s p" href="<?php echo esc_url( self::url( 'refresh_item', array( 'post' => $id ) ) ); ?>"><?php esc_html_e( 'Do it now', 'hw-trailhead' ); ?></a>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( count( $due ) > 8 ) : ?><a href="<?php echo esc_url( self::url( 'refresh' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: count */ __( 'See all %s', 'hw-trailhead' ), number_format_i18n( count( $due ) ) ) ); ?></a><?php endif; ?>
			<?php endif; ?>
		</section>
		<section class="th-grid4">
			<?php
			$cards = array(
				array( __( 'Ideas waiting for you', 'hw-trailhead' ), TH_Items::count( array( 'status' => 'new' ) ), 'ideas', __( 'Review ideas', 'hw-trailhead' ) ),
				array( __( 'Briefs in progress', 'hw-trailhead' ), TH_Items::count( array( 'status' => 'approved', 'stage' => 'brief' ) ), 'briefs', __( 'Open briefs', 'hw-trailhead' ) ),
				array( __( 'Drafts in review', 'hw-trailhead' ), TH_Items::count( array( 'status' => 'approved', 'stage' => 'draft' ) ), 'drafts', __( 'Open drafts', 'hw-trailhead' ) ),
				array( __( 'Articles on the schedule', 'hw-trailhead' ), count( array_filter( $rows, function ( $r ) { return $r['interval'] && 'none' !== $r['interval']; } ) ), 'refresh', __( 'Open schedule', 'hw-trailhead' ) ),
			);
			foreach ( $cards as $c ) :
				?>
				<div class="th-card tight"><span class="th-muted th-small"><?php echo esc_html( $c[0] ); ?></span><span class="th-stat"><?php echo esc_html( number_format_i18n( $c[1] ) ); ?></span><a class="th-btn s" href="<?php echo esc_url( self::url( $c[2] ) ); ?>"><?php echo esc_html( $c[3] ); ?></a></div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/* ---------------- Research ---------------- */

	/**
	 * Research: opportunities or a topic.
	 *
	 * @return void
	 */
	private static function view_research() {
		$tab = sanitize_key( $_GET['tab'] ?? 'opps' ); // phpcs:ignore WordPress.Security.NonceVerification
		self::head( __( 'Step 1', 'hw-trailhead' ), __( 'Research', 'hw-trailhead' ), __( 'Start from what your Search Console data already shows, or ask Claude to research a destination or topic with Ahrefs.', 'hw-trailhead' ) );
		echo '<div class="th-row"><a class="th-pill" href="' . esc_url( self::url( 'research' ) ) . '" aria-pressed="' . ( 'topic' !== $tab ? 'true' : 'false' ) . '">' . esc_html__( 'Opportunities', 'hw-trailhead' ) . '</a><a class="th-pill" href="' . esc_url( self::url( 'research', array( 'tab' => 'topic' ) ) ) . '" aria-pressed="' . ( 'topic' === $tab ? 'true' : 'false' ) . '">' . esc_html__( 'Research a topic', 'hw-trailhead' ) . '</a></div>';
		$err = get_option( 'th_last_research_error' );
		if ( $err ) {
			echo '<div class="th-notice bad">' . esc_html( sprintf( /* translators: %s: error */ __( 'Claude couldn\'t finish the last research: %s', 'hw-trailhead' ), $err ) ) . '</div>';
		}
		$last = get_option( 'th_last_research' );
		if ( $last ) {
			echo '<p class="th-muted th-small">' . esc_html( sprintf( /* translators: 1: label, 2: count, 3: time */ __( 'Last research: %1$s, %2$s ideas, %3$s ago.', 'hw-trailhead' ), $last['label'], number_format_i18n( $last['added'] ), human_time_diff( strtotime( $last['at'] . ' UTC' ) ) ) ) . ' <a href="' . esc_url( self::url( 'ideas' ) ) . '">' . esc_html__( 'See ideas', 'hw-trailhead' ) . '</a></p>';
		}
		if ( 'topic' === $tab ) {
			self::research_topic();
		} else {
			self::research_opps();
		}
	}

	/**
	 * Opportunities from Content Health's data.
	 *
	 * @return void
	 */
	private static function research_opps() {
		$o   = TH_Data::opportunities();
		$btn = function ( $id, $note, $label ) { return self::button( 'schedule_now', $label, array( 'post' => $id, 'note' => $note ), 's' ); };
		$sync = TH_Data::last_sync();
		if ( $sync ) {
			echo '<p class="th-muted th-small">' . esc_html( sprintf( /* translators: %s: time ago */ __( 'From Content Health\'s Search Console sync %s ago. Searches are the last 90 days.', 'hw-trailhead' ), human_time_diff( strtotime( $sync . ' UTC' ) ) ) ) . '</p>';
		}
		$link = function ( $id, $sub = '' ) { return '<td class="th-wide"><a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener"><b>' . esc_html( get_the_title( $id ) ) . '</b></a>' . ( $sub ? '<div class="th-muted th-small">' . $sub . '</div>' : '' ) . '</td>'; };
		?>
		<section class="th-card">
			<div class="th-row between"><h3><?php esc_html_e( 'Almost on page one', 'hw-trailhead' ); ?></h3><span class="th-chip"><?php echo esc_html( number_format_i18n( count( $o['quick'] ) ) ); ?></span></div>
			<p class="th-muted th-small"><?php esc_html_e( 'Searches where an English article ranks 4th to 15th with at least 100 impressions. A refresh aimed at that search can push it onto page one.', 'hw-trailhead' ); ?></p>
			<?php if ( $o['quick'] ) : ?>
			<div class="th-tbl"><table><thead><tr><th><?php esc_html_e( 'Search', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Article', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Position', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Impressions', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Clicks', 'hw-trailhead' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $o['quick'] as $q ) : ?>
				<tr><td><b><?php echo esc_html( $q['search'] ); ?></b></td><?php echo $link( $q['post_id'] ); // phpcs:ignore ?><td class="num">#<?php echo esc_html( number_format_i18n( $q['position'], 1 ) ); ?></td><td class="num"><?php echo esc_html( self::n( $q['impressions'] ) ); ?></td><td class="num"><?php echo esc_html( self::n( $q['clicks'] ) ); ?></td><td><?php echo $btn( $q['post_id'], sprintf( 'Push "%s" from #%s (%s impressions in 90 days)', $q['search'], number_format_i18n( $q['position'], 1 ), number_format_i18n( $q['impressions'] ) ), __( 'Refresh this page', 'hw-trailhead' ) ); // phpcs:ignore ?></td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
			<?php else : ?><p class="th-muted"><?php esc_html_e( 'Nothing in positions 4 to 15 with enough impressions.', 'hw-trailhead' ); ?></p><?php endif; ?>
		</section>

		<section class="th-card">
			<div class="th-row between"><h3><?php esc_html_e( 'Seen but rarely clicked', 'hw-trailhead' ); ?></h3><span class="th-chip"><?php echo esc_html( number_format_i18n( count( $o['ctr'] ) ) ); ?></span></div>
			<p class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %d: months */ __( 'Articles in the top 10 with at least 1,000 impressions over %d months but less than half the clicks that position usually earns. Usually a title and meta description fix.', 'hw-trailhead' ), TH_Data::window_months() ) ); ?></p>
			<?php if ( $o['ctr'] ) : ?>
			<div class="th-tbl"><table><thead><tr><th><?php esc_html_e( 'Article', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Position', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Impressions', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Click-through', 'hw-trailhead' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $o['ctr'] as $a ) : ?>
				<tr><?php echo $link( $a['id'], $a['top'] ? esc_html( sprintf( /* translators: %s: search */ __( 'Top search: %s', 'hw-trailhead' ), $a['top']['query'] ) ) : '' ); // phpcs:ignore ?><td class="num">#<?php echo esc_html( number_format_i18n( $a['position'], 1 ) ); ?></td><td class="num"><?php echo esc_html( self::n( $a['impressions'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $a['ctr'], 1 ) ); ?>% <span class="th-muted">vs ~<?php echo esc_html( $a['expected'] ); ?>%</span></td><td><?php echo $btn( $a['id'], sprintf( 'Rewrite the SEO title and meta description: %s%% click-through at #%s, around %s%% is normal', number_format_i18n( $a['ctr'], 1 ), number_format_i18n( $a['position'], 1 ), $a['expected'] ), __( 'Fix title and meta', 'hw-trailhead' ) ); // phpcs:ignore ?></td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
			<?php else : ?><p class="th-muted"><?php esc_html_e( 'No articles stand out.', 'hw-trailhead' ); ?></p><?php endif; ?>
		</section>

		<section class="th-card">
			<div class="th-row between"><h3><?php esc_html_e( 'Losing clicks', 'hw-trailhead' ); ?></h3><span class="th-chip"><?php echo esc_html( number_format_i18n( count( $o['losing'] ) ) ); ?></span></div>
			<p class="th-muted th-small"><?php esc_html_e( 'Articles with at least 30 clicks in the previous 90 days that lost a quarter or more in the last 90 days.', 'hw-trailhead' ); ?></p>
			<?php if ( $o['losing'] ) : ?>
			<div class="th-tbl"><table><thead><tr><th><?php esc_html_e( 'Article', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Clicks before → now', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Change', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Position', 'hw-trailhead' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $o['losing'] as $a ) : ?>
				<tr><?php echo $link( $a['id'], $a['top'] ? esc_html( sprintf( /* translators: %s: search */ __( 'Top search: %s', 'hw-trailhead' ), $a['top']['query'] ) ) : '' ); // phpcs:ignore ?><td class="num"><?php echo esc_html( self::n( $a['prior'] ) . ' → ' . self::n( $a['recent'] ) ); ?></td><td class="num"><span class="th-chip bad"><?php echo esc_html( $a['change'] ); ?>%</span></td><td class="num"><?php echo $a['position'] ? '#' . esc_html( number_format_i18n( $a['position'], 1 ) ) : '–'; ?></td><td><?php echo $btn( $a['id'], sprintf( 'Clicks fell from %s to %s (%s%%) over 90 days', number_format_i18n( $a['prior'] ), number_format_i18n( $a['recent'] ), $a['change'] ), __( 'Refresh this page', 'hw-trailhead' ) ); // phpcs:ignore ?></td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
			<?php else : ?><p class="th-muted"><?php esc_html_e( 'No big drops.', 'hw-trailhead' ); ?></p><?php endif; ?>
		</section>

		<section class="th-card">
			<div class="th-row between"><h3><?php esc_html_e( 'Competitor gaps', 'hw-trailhead' ); ?></h3><?php echo self::waiting_chip( TH_Tasks::waiting_for( 'gaps' ) ); // phpcs:ignore ?></div>
			<p class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %s: competitors */ __( 'Claude looks up informational searches with 200+ monthly searches where %s rank in the top 10, drops the ones the blog already appears for, and turns the rest into scored ideas. Uses about 6,000 Ahrefs units.', 'hw-trailhead' ), implode( ', ', TH_Settings::competitors() ) ) ); ?></p>
			<div><?php echo self::button( 'gaps', __( 'Ask Claude to find competitor gaps', 'hw-trailhead' ) ); // phpcs:ignore ?></div>
		</section>
		<?php
	}

	/**
	 * Research a topic.
	 *
	 * @return void
	 */
	private static function research_topic() {
		$waiting = array_filter( TH_Tasks::waiting(), function ( $t ) { return 'research' === get_post_meta( $t->ID, '_th_type', true ); } );
		?>
		<div class="th-cols">
			<section class="th-card th-col-main">
				<h3><?php esc_html_e( 'What to research', 'hw-trailhead' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="th-stack">
					<?php echo TH_Actions::fields( 'research' ); // phpcs:ignore ?>
					<label class="th-f" for="th-seeds"><?php esc_html_e( 'Destinations or topics, separated by commas', 'hw-trailhead' ); ?>
						<input id="th-seeds" name="seeds" class="th-in" placeholder="<?php esc_attr_e( 'e.g. Lisbon, Porto, solo travel', 'hw-trailhead' ); ?>" required>
					</label>
					<?php if ( TH_Ahrefs::ready() ) : ?>
						<p class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: 1: keywords, 2: competitors */ __( 'WordPress gets %1$d Ahrefs keywords per seed and what %2$s rank for, using your API key. See them straight away, or send them to Claude to turn into scored ideas.', 'hw-trailhead' ), TH_Settings::get( 'kw_per_seed' ), implode( ', ', TH_Settings::competitors() ) ) ); ?></p>
						<div class="th-row tight"><button class="th-btn p" name="go" value="keywords"><?php esc_html_e( 'Show keywords now', 'hw-trailhead' ); ?></button><button class="th-btn"><?php esc_html_e( 'Send to Claude for ideas', 'hw-trailhead' ); ?></button></div>
					<?php else : ?>
						<p class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: 1: keywords, 2: competitors */ __( 'Claude pulls %1$d Ahrefs keywords per seed and what %2$s rank for, compares them with your Search Console data, and turns them into scored ideas. Add your Ahrefs API key in Settings to see keywords straight away.', 'hw-trailhead' ), TH_Settings::get( 'kw_per_seed' ), implode( ', ', TH_Settings::competitors() ) ) ); ?></p>
						<div><button class="th-btn p"><?php esc_html_e( 'Queue research for Claude', 'hw-trailhead' ); ?></button></div>
					<?php endif; ?>
				</form>
			</section>
			<aside class="th-card th-col-side">
				<h3><?php esc_html_e( 'Waiting for Claude', 'hw-trailhead' ); ?></h3>
				<?php if ( ! $waiting ) : ?>
					<p class="th-muted th-small"><?php esc_html_e( 'Nothing queued.', 'hw-trailhead' ); ?></p>
				<?php else : ?>
					<?php foreach ( $waiting as $t ) : ?><div class="th-small"><b><?php echo esc_html( get_post_meta( $t->ID, '_th_label', true ) ); ?></b><br><span class="th-muted"><?php echo esc_html( sprintf( /* translators: %s: time */ __( 'queued %s ago', 'hw-trailhead' ), human_time_diff( strtotime( $t->post_date_gmt . ' UTC' ) ) ) ); ?></span></div><?php endforeach; ?>
				<?php endif; ?>
			</aside>
		</div>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_GET['kw'] ) ) {
			self::keyword_results();
		}
	}

	/**
	 * The Ahrefs keywords WordPress fetched, before Claude sees them.
	 *
	 * @return void
	 */
	private static function keyword_results() {
		$kw = get_transient( 'th_kw_' . get_current_user_id() );
		if ( ! is_array( $kw ) ) {
			return;
		}
		$all = array();
		foreach ( $kw['data']['keywords'] as $rows ) {
			foreach ( $rows as $r ) {
				$all[] = (string) ( $r['keyword'] ?? '' );
			}
		}
		$ours = TH_Ahrefs::ours( $all );
		echo '<section class="th-card"><div class="th-row between"><h3>' . esc_html( sprintf( /* translators: %s: seeds */ __( 'Ahrefs keywords: %s', 'hw-trailhead' ), implode( ', ', $kw['seeds'] ) ) ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . TH_Actions::fields( 'keywords_to_claude' ) . '<button class="th-btn p s">' . esc_html__( 'Turn these into ideas with Claude', 'hw-trailhead' ) . '</button></form></div>'; // phpcs:ignore
		echo '<p class="th-muted th-small">' . esc_html( sprintf( /* translators: %s: time */ __( 'Fetched %s ago. "We rank" comes from your Search Console data (last 90 days).', 'hw-trailhead' ), human_time_diff( (int) $kw['at'] ) ) ) . '</p>';
		foreach ( $kw['data']['keywords'] as $seed => $rows ) {
			echo '<h3 class="th-mt">' . esc_html( $seed ) . '</h3>';
			if ( ! $rows ) {
				echo '<p class="th-muted th-small">' . esc_html__( 'Ahrefs has no keywords for this.', 'hw-trailhead' ) . '</p>';
				continue;
			}
			echo '<div class="th-tbl"><table><thead><tr><th>' . esc_html__( 'Keyword', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Volume', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Difficulty', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Traffic potential', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Parent topic', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'We rank', 'hw-trailhead' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$o = $ours[ strtolower( (string) ( $r['keyword'] ?? '' ) ) ] ?? null;
				echo '<tr><td>' . esc_html( $r['keyword'] ?? '' ) . '</td><td class="num">' . esc_html( isset( $r['volume'] ) ? self::n( (int) $r['volume'] ) : '–' ) . '</td><td class="num">' . esc_html( $r['difficulty'] ?? '–' ) . '</td><td class="num">' . esc_html( isset( $r['traffic_potential'] ) ? self::n( (int) $r['traffic_potential'] ) : '–' ) . '</td><td>' . esc_html( $r['parent_topic'] ?? '' ) . '</td><td>' . ( $o ? '<a href="' . esc_url( $o['url'] ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( /* translators: %s: position */ __( '#%s', 'hw-trailhead' ), number_format_i18n( $o['position'], 1 ) ) ) . '</a>' : '<span class="th-muted">–</span>' ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			$comp = $kw['data']['competitors'][ $seed ] ?? array();
			if ( $comp ) {
				echo '<details class="th-mt"><summary>' . esc_html__( 'What competitors rank top 10 for', 'hw-trailhead' ) . '</summary>';
				foreach ( $comp as $domain => $crows ) {
					echo '<p class="th-small th-mt"><b>' . esc_html( $domain ) . '</b></p><div class="th-tbl"><table><thead><tr><th>' . esc_html__( 'Keyword', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Position', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Volume', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Their page', 'hw-trailhead' ) . '</th></tr></thead><tbody>';
					foreach ( $crows as $c ) {
						echo '<tr><td>' . esc_html( $c['keyword'] ?? '' ) . '</td><td class="num">' . esc_html( $c['best_position'] ?? '' ) . '</td><td class="num">' . esc_html( isset( $c['volume'] ) ? self::n( (int) $c['volume'] ) : '–' ) . '</td><td>' . ( ! empty( $c['best_position_url'] ) ? '<a href="' . esc_url( $c['best_position_url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $c['best_position_url'], PHP_URL_PATH ) ) . '</a>' : '' ) . '</td></tr>';
					}
					echo '</tbody></table></div>';
				}
				echo '</details>';
			}
		}
		echo '</section>';
	}

	/* ---------------- Ideas ---------------- */

	/**
	 * Ideas.
	 *
	 * @return void
	 */
	private static function view_ideas() {
		$f       = sanitize_key( $_GET['filter'] ?? 'waiting' ); // phpcs:ignore WordPress.Security.NonceVerification
		$v       = sanitize_key( $_GET['verdict'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$statusf = array( 'waiting' => 'new', 'approved' => 'approved', 'skipped' => 'skipped' );
		$where   = array( 'status' => $statusf[ $f ] ?? 'new' );
		if ( in_array( $v, array( 'new', 'refresh', 'skip' ), true ) ) {
			$where['verdict'] = $v;
		}
		$items = TH_Items::find( $where );
		self::head( __( 'Step 2', 'hw-trailhead' ), __( 'Ideas', 'hw-trailhead' ), __( 'Each idea is scored and marked new article, refresh or skip, with the reason. Approve the ones worth a brief.', 'hw-trailhead' ), '<a class="th-btn" href="' . esc_url( self::url( 'research' ) ) . '">' . esc_html__( 'Find more', 'hw-trailhead' ) . '</a>' );
		echo '<div class="th-row">';
		foreach ( array( 'waiting' => __( 'Waiting', 'hw-trailhead' ), 'approved' => __( 'Approved', 'hw-trailhead' ), 'skipped' => __( 'Skipped', 'hw-trailhead' ) ) as $k => $l ) {
			echo '<a class="th-pill" aria-pressed="' . ( $f === $k ? 'true' : 'false' ) . '" href="' . esc_url( self::url( 'ideas', array( 'filter' => $k ) ) ) . '">' . esc_html( $l ) . ' <span class="th-muted">' . esc_html( number_format_i18n( TH_Items::count( array( 'status' => $statusf[ $k ] ) ) ) ) . '</span></a>';
		}
		echo '<span class="th-sep"></span>';
		foreach ( array( '' => __( 'Any verdict', 'hw-trailhead' ), 'new' => __( 'New article', 'hw-trailhead' ), 'refresh' => __( 'Refresh', 'hw-trailhead' ), 'skip' => __( 'Skip', 'hw-trailhead' ) ) as $k => $l ) {
			echo '<a class="th-pill" aria-pressed="' . ( $v === $k ? 'true' : 'false' ) . '" href="' . esc_url( self::url( 'ideas', array( 'filter' => $f, 'verdict' => $k ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</div>';
		if ( ! $items ) {
			echo '<div class="th-empty"><b>' . esc_html__( 'Nothing here yet.', 'hw-trailhead' ) . '</b><span>' . esc_html__( 'Queue research on a destination or competitor gaps, and the ideas land here after Claude\'s next run.', 'hw-trailhead' ) . '</span><a class="th-btn p" href="' . esc_url( self::url( 'research' ) ) . '">' . esc_html__( 'Go to research', 'hw-trailhead' ) . '</a></div>';
			return;
		}
		foreach ( $items as $i ) {
			$d     = $i['data'];
			$color = $i['score'] >= 75 ? 'good' : ( $i['score'] >= 50 ? 'mid' : 'low' );
			?>
			<article class="th-card">
				<div class="th-idea">
					<div class="th-score <?php echo esc_attr( $color ); ?>"><?php echo esc_html( $i['score'] ); ?></div>
					<div class="th-grow">
						<div class="th-row tight"><span class="th-chip <?php echo esc_attr( 'new' === $i['verdict'] ? 'new' : ( 'refresh' === $i['verdict'] ? 'refresh' : '' ) ); ?>"><?php echo esc_html( 'new' === $i['verdict'] ? __( 'New article', 'hw-trailhead' ) : ( 'refresh' === $i['verdict'] ? __( 'Refresh', 'hw-trailhead' ) : __( 'Skip', 'hw-trailhead' ) ) ); ?></span><?php if ( ! empty( $d['source'] ) ) : ?><span class="th-chip"><?php echo esc_html( $d['source'] ); ?></span><?php endif; ?></div>
						<h3 class="th-mt"><?php echo esc_html( $i['title'] ); ?></h3>
						<div class="th-meta"><span><?php esc_html_e( 'Keyword', 'hw-trailhead' ); ?> <b><?php echo esc_html( $i['keyword'] ); ?></b></span><span><?php esc_html_e( 'Volume', 'hw-trailhead' ); ?> <b><?php echo esc_html( self::n( $d['volume'] ?? null ) ); ?></b></span><span><?php esc_html_e( 'Difficulty', 'hw-trailhead' ); ?> <b><?php echo esc_html( self::n( $d['kd'] ?? null ) ); ?></b></span></div>
						<p class="th-p"><?php echo esc_html( $i['reason'] ); ?></p>
						<details><summary><?php esc_html_e( 'Evidence', 'hw-trailhead' ); ?></summary>
							<div class="th-row stretch th-mt">
								<div class="th-ev"><h4><?php esc_html_e( 'Competitors in the top 10', 'hw-trailhead' ); ?></h4><?php if ( empty( $d['competitors'] ) ) { esc_html_e( 'None recorded.', 'hw-trailhead' ); } foreach ( (array) ( $d['competitors'] ?? array() ) as $c ) { echo '<div>' . esc_html( $c['domain'] . ' #' . $c['position'] ) . ( $c['url'] ? ' <a href="' . esc_url( $c['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'page', 'hw-trailhead' ) . '</a>' : '' ) . '</div>'; } ?></div>
								<div class="th-ev"><h4><?php esc_html_e( 'Keywords in this idea', 'hw-trailhead' ); ?></h4><?php echo esc_html( implode( ', ', (array) ( $d['cluster'] ?? array() ) ) ?: '–' ); ?></div>
								<div class="th-ev"><h4><?php esc_html_e( 'Our closest article', 'hw-trailhead' ); ?></h4><?php echo ! empty( $d['our_url'] ) ? '<a href="' . esc_url( $d['our_url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $d['our_url'], PHP_URL_PATH ) ) . '</a>' : esc_html__( 'None ranks for this yet.', 'hw-trailhead' ); ?></div>
							</div>
						</details>
					</div>
					<div class="th-row tight">
						<?php
						if ( 'new' === $i['status'] ) {
							echo self::button( 'idea_skip', __( 'Skip', 'hw-trailhead' ), array( 'id' => $i['id'], 'filter' => $f ) ) . self::button( 'idea_approve', __( 'Approve', 'hw-trailhead' ), array( 'id' => $i['id'] ), 'p' ); // phpcs:ignore
						} elseif ( 'skipped' === $i['status'] ) {
							echo self::button( 'idea_unskip', __( 'Move back', 'hw-trailhead' ), array( 'id' => $i['id'], 'filter' => $f ) ); // phpcs:ignore
						} elseif ( in_array( $i['stage'], array( 'draft', 'ready' ), true ) ) {
							echo '<a class="th-btn" href="' . esc_url( self::url( 'drafts', array( 'item' => $i['id'] ) ) ) . '">' . esc_html__( 'Open draft', 'hw-trailhead' ) . '</a>';
						} elseif ( 'brief' === $i['stage'] ) {
							echo '<a class="th-btn" href="' . esc_url( self::url( 'briefs', array( 'item' => $i['id'] ) ) ) . '">' . esc_html__( 'Open brief', 'hw-trailhead' ) . '</a>';
						}
						?>
					</div>
				</div>
			</article>
			<?php
		}
	}

	/* ---------------- Start an article ---------------- */

	/**
	 * Start an article from a keyword you already have in mind.
	 *
	 * @return void
	 */
	private static function view_start() {
		self::head( __( 'Create', 'hw-trailhead' ), __( 'New article', 'hw-trailhead' ), __( 'Skip research when you already know what to write. Give the target keyword and, if you like, a direction. Claude builds the brief from your Search Console and Ahrefs data and adds what the top results are missing.', 'hw-trailhead' ) );
		$authors = TH_Settings::authors();
		$pl      = TH_Bridge::plan( (int) ( $_GET['plan'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $pl ) {
			echo '<div class="th-notice ok">' . esc_html( sprintf( /* translators: %s: title */ __( 'From the Content planner: %s. Check the keyword, pick the author and add any direction, then start.', 'hw-trailhead' ), $pl['title'] ) ) . '</div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="th-card th-stack">
			<?php echo TH_Actions::fields( 'start', $pl ? array( 'plan' => $pl['id'] ) : array() ); // phpcs:ignore ?>
			<input type="hidden" name="queue" value="1">
			<div class="th-row stretch">
				<label class="th-f th-grow" for="th-kw-start"><?php esc_html_e( 'Target keyword', 'hw-trailhead' ); ?><input id="th-kw-start" name="keyword" class="th-in" required value="<?php echo esc_attr( $pl ? $pl['keyword'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. is lisbon safe', 'hw-trailhead' ); ?>"></label>
				<label class="th-f th-grow" for="th-title-start"><?php esc_html_e( 'Working title (optional)', 'hw-trailhead' ); ?><input id="th-title-start" name="title" class="th-in" value="<?php echo esc_attr( $pl ? $pl['title'] : '' ); ?>"></label>
			</div>
			<label class="th-f" for="th-author"><?php esc_html_e( 'Author and voice', 'hw-trailhead' ); ?><select id="th-author" name="author" class="th-in"><?php foreach ( $authors as $name => $voice ) { echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $name . ( $voice ? ' · ' . wp_trim_words( $voice, 8 ) : '' ) ) . '</option>'; } ?></select></label>
			<label class="th-f" for="th-dir"><?php esc_html_e( 'Direction (optional)', 'hw-trailhead' ); ?><textarea id="th-dir" name="direction" rows="3" class="th-in" placeholder="<?php esc_attr_e( 'The angle, who it is for, what it must cover, what to avoid.', 'hw-trailhead' ); ?>"></textarea></label>
			<label class="th-f" for="th-ctx"><?php esc_html_e( 'Your own input (optional)', 'hw-trailhead' ); ?><textarea id="th-ctx" name="notes" rows="5" class="th-in" placeholder="<?php esc_attr_e( 'Interview notes, quotes from hostel staff or travellers, your own experience, Reddit threads. This is the information nobody else has.', 'hw-trailhead' ); ?>"><?php echo esc_textarea( $pl ? $pl['notes'] : '' ); ?></textarea></label>
			<?php if ( $pl && $pl['searches'] ) : ?>
				<div class="th-small"><span class="th-muted"><?php echo esc_html( sprintf( /* translators: %s: impressions */ __( 'Search Console data from the planner goes to Claude with the brief (%s impressions):', 'hw-trailhead' ), number_format_i18n( $pl['impressions'] ) ) ); ?></span>
					<div class="th-row tight th-mt"><?php foreach ( array_slice( $pl['searches'], 0, 12 ) as $q ) { echo '<span class="th-chip">' . esc_html( $q['search'] . ' · ' . number_format_i18n( $q['impressions'] ) ) . '</span>'; } ?></div>
				</div>
			<?php endif; ?>
			<div><button class="th-btn p"><?php esc_html_e( 'Start and ask Claude for the brief', 'hw-trailhead' ); ?></button></div>
		</form>
		<?php
	}

	/* ---------------- Briefs ---------------- */

	/**
	 * Side list of items.
	 *
	 * @param array[] $items Items.
	 * @param int     $sel   Selected.
	 * @param string  $view  View.
	 * @param callable $sub  Sub-label.
	 * @return void
	 */
	private static function item_list( $items, $sel, $view, $sub ) {
		echo '<aside class="th-col-list"><div class="th-list">';
		foreach ( $items as $i ) {
			echo '<a class="th-li" href="' . esc_url( self::url( $view, array( 'item' => $i['id'] ) ) ) . '"' . ( $i['id'] === $sel ? ' aria-current="true"' : '' ) . '><span><b>' . esc_html( $i['title'] ) . '</b><br><span class="th-muted th-small">' . esc_html( $sub( $i ) ) . '</span></span></a>';
		}
		echo '</div></aside>';
	}

	/**
	 * Briefs.
	 *
	 * @return void
	 */
	private static function view_briefs() {
		$items = TH_Items::find( array( 'status' => 'approved', 'stage' => 'brief' ) );
		self::head( __( 'Step 3', 'hw-trailhead' ), __( 'Briefs', 'hw-trailhead' ), __( 'Claude writes the brief from the research and your style guide. Edit any field, or ask for changes in plain words.', 'hw-trailhead' ) );
		if ( ! $items ) {
			echo '<div class="th-empty"><b>' . esc_html__( 'No approved ideas waiting for a brief.', 'hw-trailhead' ) . '</b><a class="th-btn p" href="' . esc_url( self::url( 'ideas' ) ) . '">' . esc_html__( 'Go to ideas', 'hw-trailhead' ) . '</a></div>';
			return;
		}
		$sel = (int) ( $_GET['item'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$ids = wp_list_pluck( $items, 'id' );
		$sel = in_array( $sel, $ids, true ) ? $sel : $ids[0];
		$i   = TH_Items::get( $sel );
		echo '<div class="th-cols">';
		self::item_list( $items, $sel, 'briefs', function ( $x ) { return TH_Tasks::waiting_for( 'item:' . $x['id'] ) ? __( 'Waiting for Claude', 'hw-trailhead' ) : ( $x['brief'] ? __( 'Written, waiting for you', 'hw-trailhead' ) : __( 'To write', 'hw-trailhead' ) ); } );
		echo '<div class="th-col-main">';
		self::brief_workspace( $i );
		echo '</div></div>';
	}

	/**
	 * One brief.
	 *
	 * @param array $i Item.
	 * @return void
	 */
	private static function brief_workspace( $i ) {
		$w   = TH_Tasks::waiting_for( 'item:' . $i['id'] );
		$b   = $i['brief'];
		$err = $i['data']['error'] ?? '';
		$act = esc_url( admin_url( 'admin-post.php' ) );
		echo '<section class="th-card"><div class="th-row between"><div><span class="th-chip ' . ( 'refresh' === $i['verdict'] ? 'refresh' : 'new' ) . '">' . esc_html( 'refresh' === $i['verdict'] ? __( 'Refresh idea', 'hw-trailhead' ) : __( 'New article', 'hw-trailhead' ) ) . '</span><h3 class="th-mt">' . esc_html( $i['title'] ) . '</h3><div class="th-meta"><span>' . esc_html__( 'Keyword', 'hw-trailhead' ) . ' <b>' . esc_html( $i['keyword'] ) . '</b></span><span>' . esc_html__( 'Volume', 'hw-trailhead' ) . ' <b>' . esc_html( self::n( $i['data']['volume'] ?? null ) ) . '</b></span></div></div>' . self::waiting_chip( $w ) . '</div></section>'; // phpcs:ignore
		if ( ! empty( $i['data']['author'] ) || ! empty( $i['data']['direction'] ) ) {
			echo '<p class="th-small th-muted">' . esc_html( trim( ( ! empty( $i['data']['author'] ) ? __( 'Voice: ', 'hw-trailhead' ) . $i['data']['author'] . '. ' : '' ) . ( ! empty( $i['data']['direction'] ) ? __( 'Direction: ', 'hw-trailhead' ) . $i['data']['direction'] : '' ) ) ) . '</p>';
		}
		if ( $err ) {
			echo '<div class="th-notice bad">' . esc_html( sprintf( /* translators: %s: error */ __( 'Claude couldn\'t do the last request: %s', 'hw-trailhead' ), $err ) ) . '</div>';
		}
		if ( ! $b ) {
			?>
			<section class="th-card">
				<form method="post" action="<?php echo $act; // phpcs:ignore ?>" class="th-stack">
					<?php echo TH_Actions::fields( 'brief_queue', array( 'id' => $i['id'] ) ); // phpcs:ignore ?>
					<label class="th-f" for="th-notes"><?php esc_html_e( 'Your own input for Claude (optional)', 'hw-trailhead' ); ?>
						<textarea id="th-notes" name="notes" rows="6" class="th-in" placeholder="<?php esc_attr_e( 'Interview notes, quotes, your own experience, Reddit threads, or anything the brief must include. This is the information nobody else has.', 'hw-trailhead' ); ?>"><?php echo esc_textarea( $i['data']['notes'] ?? '' ); ?></textarea></label>
					<div><button class="th-btn p" <?php disabled( (bool) $w ); ?>><?php echo esc_html( $w ? __( 'Brief queued for Claude', 'hw-trailhead' ) : __( 'Ask Claude to write the brief', 'hw-trailhead' ) ); ?></button></div>
				</form>
			</section>
			<?php
			return;
		}
		$chosen = (int) ( $b['chosen'] ?? 0 );
		?>
		<div class="th-cols">
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>" class="th-col-main th-stack">
				<?php echo TH_Actions::fields( 'brief_save', array( 'id' => $i['id'] ) ); // phpcs:ignore ?>
				<section class="th-card"><h3><?php esc_html_e( 'Title', 'hw-trailhead' ); ?></h3>
					<?php foreach ( (array) ( $b['titles'] ?? array() ) as $k => $t ) : ?>
						<label class="th-opt"><input type="radio" name="chosen" value="<?php echo esc_attr( $k ); ?>" <?php checked( $chosen, $k ); ?>> <?php echo esc_html( $t ); ?></label>
					<?php endforeach; ?>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Target', 'hw-trailhead' ); ?></h3>
					<div class="th-row stretch">
						<label class="th-f th-grow" for="th-intent"><?php esc_html_e( 'Search intent', 'hw-trailhead' ); ?><textarea id="th-intent" name="intent" rows="2" class="th-in"><?php echo esc_textarea( $b['intent'] ?? '' ); ?></textarea></label>
						<label class="th-f" for="th-length"><?php esc_html_e( 'Length', 'hw-trailhead' ); ?><input id="th-length" name="length" class="th-in" value="<?php echo esc_attr( $b['length'] ?? '' ); ?>"></label>
					</div>
					<label class="th-f" for="th-sec"><?php esc_html_e( 'Also cover (one per line)', 'hw-trailhead' ); ?><textarea id="th-sec" name="secondary" rows="3" class="th-in"><?php echo esc_textarea( implode( "\n", (array) ( $b['secondary'] ?? array() ) ) ); ?></textarea></label>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Outline', 'hw-trailhead' ); ?></h3>
					<?php foreach ( (array) ( $b['outline'] ?? array() ) as $k => $o ) : ?>
						<div class="th-oli"><span class="n"><?php echo esc_html( $k + 1 ); ?></span><div class="th-grow th-stack tight">
							<label class="screen-reader-text" for="th-h2-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Heading', 'hw-trailhead' ); ?></label><input id="th-h2-<?php echo esc_attr( $k ); ?>" class="th-in" name="h2[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $o['h2'] ?? '' ); ?>">
							<label class="screen-reader-text" for="th-hn-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Notes', 'hw-trailhead' ); ?></label><textarea id="th-hn-<?php echo esc_attr( $k ); ?>" class="th-in" rows="2" name="h2notes[<?php echo esc_attr( $k ); ?>]"><?php echo esc_textarea( $o['notes'] ?? '' ); ?></textarea>
						</div></div>
					<?php endforeach; ?>
					<label class="th-f" for="th-newh2"><?php esc_html_e( 'Add a section (heading)', 'hw-trailhead' ); ?><input id="th-newh2" class="th-in" name="new_h2"></label>
					<p class="th-muted th-small"><?php esc_html_e( 'Clear a heading to remove its section.', 'hw-trailhead' ); ?></p>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Questions to answer', 'hw-trailhead' ); ?></h3>
					<label class="screen-reader-text" for="th-q"><?php esc_html_e( 'Questions, one per line', 'hw-trailhead' ); ?></label><textarea id="th-q" name="questions" rows="4" class="th-in"><?php echo esc_textarea( implode( "\n", (array) ( $b['questions'] ?? array() ) ) ); ?></textarea>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Information gain', 'hw-trailhead' ); ?></h3>
					<p class="th-muted th-small"><?php esc_html_e( 'What this article adds that the top results don\'t, with sources. One per line: what it adds | source URL | kind.', 'hw-trailhead' ); ?></p>
					<?php
					$src_lines = array();
					foreach ( (array) ( $b['sources'] ?? array() ) as $src ) {
						$src_lines[] = trim( ( $src['what'] ?? '' ) . ' | ' . ( $src['url'] ?? '' ) . ' | ' . ( $src['kind'] ?? '' ), ' |' );
					}
					?>
					<label class="screen-reader-text" for="th-src"><?php esc_html_e( 'Sources', 'hw-trailhead' ); ?></label><textarea id="th-src" name="sources" rows="5" class="th-in"><?php echo esc_textarea( implode( "\n", $src_lines ) ); ?></textarea>
					<?php if ( ! empty( $b['blocks'] ) ) : ?><div class="th-small"><span class="th-muted"><?php esc_html_e( 'Blocks Claude plans to use', 'hw-trailhead' ); ?></span><?php foreach ( (array) $b['blocks'] as $bl ) { echo '<div>· ' . esc_html( $bl ) . '</div>'; } ?></div><?php endif; ?>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Links and search appearance', 'hw-trailhead' ); ?></h3>
					<label class="th-f" for="th-links"><?php esc_html_e( 'Internal links (one URL per line)', 'hw-trailhead' ); ?><textarea id="th-links" name="links" rows="3" class="th-in"><?php echo esc_textarea( implode( "\n", (array) ( $b['links'] ?? array() ) ) ); ?></textarea></label>
					<label class="th-f" for="th-seo"><?php esc_html_e( 'SEO title', 'hw-trailhead' ); ?><input id="th-seo" name="seoTitle" class="th-in" value="<?php echo esc_attr( $b['seoTitle'] ?? '' ); ?>"></label>
					<label class="th-f" for="th-meta"><?php esc_html_e( 'Meta description', 'hw-trailhead' ); ?><textarea id="th-meta" name="meta" rows="2" class="th-in"><?php echo esc_textarea( $b['meta'] ?? '' ); ?></textarea></label>
					<?php if ( ! empty( $b['check'] ) ) : ?><div class="th-notice"><?php echo esc_html( __( 'Check before drafting: ', 'hw-trailhead' ) . implode( ' · ', (array) $b['check'] ) ); ?></div><?php endif; ?>
				</section>
				<div><button class="th-btn"><?php esc_html_e( 'Save brief', 'hw-trailhead' ); ?></button></div>
			</form>
			<aside class="th-col-side th-stack">
				<section class="th-card hot"><h3><?php esc_html_e( 'Ask for changes', 'hw-trailhead' ); ?></h3>
					<form method="post" action="<?php echo $act; // phpcs:ignore ?>" class="th-stack">
						<?php echo TH_Actions::fields( 'brief_revise', array( 'id' => $i['id'] ) ); // phpcs:ignore ?>
						<label class="screen-reader-text" for="th-ask"><?php esc_html_e( 'What to change', 'hw-trailhead' ); ?></label>
						<textarea id="th-ask" name="ask" rows="4" class="th-in" placeholder="<?php esc_attr_e( 'e.g. Add a section on night buses and make the tone more upbeat', 'hw-trailhead' ); ?>" required></textarea>
						<button class="th-btn d" <?php disabled( (bool) $w ); ?>><?php esc_html_e( 'Send to Claude', 'hw-trailhead' ); ?></button>
					</form>
					<?php if ( ! empty( $i['data']['history'] ) ) : ?><div class="th-small"><span class="th-muted"><?php esc_html_e( 'History', 'hw-trailhead' ); ?></span><?php foreach ( array_slice( (array) $i['data']['history'], -3 ) as $h ) { echo '<div>' . esc_html( $h ) . '</div>'; } ?></div><?php endif; ?>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Ready?', 'hw-trailhead' ); ?></h3>
					<p class="th-muted th-small"><?php esc_html_e( 'Save any edits first. Approving queues the draft for Claude.', 'hw-trailhead' ); ?></p>
					<?php echo self::button( 'brief_approve', __( 'Approve and write draft', 'hw-trailhead' ), array( 'id' => $i['id'] ), 'p' ); // phpcs:ignore ?>
					<?php echo self::button( 'brief_redo', __( 'Start the brief again', 'hw-trailhead' ), array( 'id' => $i['id'] ), 'link' ); // phpcs:ignore ?>
				</section>
			</aside>
		</div>
		<?php
	}

	/* ---------------- Drafts ---------------- */

	/**
	 * Drafts.
	 *
	 * @return void
	 */
	private static function view_drafts() {
		$items = TH_Items::find( array( 'status' => 'approved', 'stage' => array( 'draft', 'ready' ) ) );
		self::head( __( 'Step 4', 'hw-trailhead' ), __( 'Drafts', 'hw-trailhead' ), __( 'Claude writes the article into a WordPress draft. Anything that can go out of date is highlighted for you to check.', 'hw-trailhead' ) );
		if ( ! $items ) {
			echo '<div class="th-empty"><b>' . esc_html__( 'No drafts yet.', 'hw-trailhead' ) . '</b><a class="th-btn p" href="' . esc_url( self::url( 'briefs' ) ) . '">' . esc_html__( 'Go to briefs', 'hw-trailhead' ) . '</a></div>';
			return;
		}
		$sel = (int) ( $_GET['item'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$ids = wp_list_pluck( $items, 'id' );
		$sel = in_array( $sel, $ids, true ) ? $sel : $ids[0];
		$i   = TH_Items::get( $sel );
		echo '<div class="th-cols">';
		self::item_list( $items, $sel, 'drafts', function ( $x ) { return 'ready' === $x['stage'] ? __( 'Reviewed', 'hw-trailhead' ) : ( TH_Tasks::waiting_for( 'item:' . $x['id'] ) ? __( 'Waiting for Claude', 'hw-trailhead' ) : ( $x['draft_post_id'] ? __( 'In review', 'hw-trailhead' ) : '' ) ); } );
		echo '<div class="th-col-main">';
		self::draft_workspace( $i );
		echo '</div></div>';
	}

	/**
	 * One draft.
	 *
	 * @param array $i Item.
	 * @return void
	 */
	private static function draft_workspace( $i ) {
		$w   = TH_Tasks::waiting_for( 'item:' . $i['id'] );
		$p   = $i['draft_post_id'] ? get_post( $i['draft_post_id'] ) : null;
		$err = $i['data']['error'] ?? '';
		$act = esc_url( admin_url( 'admin-post.php' ) );
		if ( $err ) {
			echo '<div class="th-notice bad">' . esc_html( sprintf( /* translators: %s: error */ __( 'Claude couldn\'t do the last request: %s', 'hw-trailhead' ), $err ) ) . '</div>';
		}
		if ( ! $p ) {
			echo '<section class="th-card"><h3>' . esc_html( $i['title'] ) . '</h3>' . self::waiting_chip( $w ) . '<p class="th-muted">' . esc_html__( 'The brief is approved and the draft is queued for Claude. It appears here, and as a draft post in WordPress, after the next run.', 'hw-trailhead' ) . '</p>' . self::button( 'back_to_brief', __( 'Back to the brief', 'hw-trailhead' ), array( 'id' => $i['id'] ) ) . '</section>'; // phpcs:ignore
			return;
		}
		$facts   = TH_Actions::facts( $p->post_content );
		$checked = array_map( 'intval', (array) $i['checked'] );
		$left    = count( array_diff( array_keys( $facts ), $checked ) );
		$images  = array();
		preg_match_all( '#<figure data-image="([^"]*)"#', $p->post_content, $m );
		$images = $m[1];
		?>
		<div class="th-cols">
			<section class="th-card th-col-main">
				<div class="th-row between"><span class="th-chip <?php echo 'ready' === $i['stage'] ? 'ok' : 'warn'; ?>"><?php echo esc_html( 'ready' === $i['stage'] ? __( 'Reviewed', 'hw-trailhead' ) : __( 'Draft', 'hw-trailhead' ) ); ?></span><?php echo self::waiting_chip( $w ); // phpcs:ignore ?><a class="th-btn s" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php esc_html_e( 'Open in the editor', 'hw-trailhead' ); ?></a></div>
				<h2 class="th-h1"><?php echo esc_html( get_the_title( $p ) ); ?></h2>
				<div class="th-article"><?php echo do_shortcode( wp_kses( $p->post_content, wp_kses_allowed_html( 'post' ) + array( 'mark' => array(), 'figure' => array( 'data-image' => true ) ) ) ); // phpcs:ignore ?></div>
				<p class="th-muted th-small"><?php esc_html_e( 'Shortcodes are shown as they render on this screen; check the final look in Preview.', 'hw-trailhead' ); ?></p>
			</section>
			<aside class="th-col-side th-stack">
				<form method="post" action="<?php echo $act; // phpcs:ignore ?>" class="th-stack">
					<?php echo TH_Actions::fields( 'draft_save', array( 'id' => $i['id'] ) ); // phpcs:ignore ?>
					<section class="th-card"><div class="th-row between"><h3><?php esc_html_e( 'Facts to check', 'hw-trailhead' ); ?></h3><span class="th-chip <?php echo $left ? 'warn' : 'ok'; ?>"><?php echo esc_html( ( count( $facts ) - $left ) . ' / ' . count( $facts ) ); ?></span></div>
						<?php if ( ! $facts ) : ?><span class="th-muted th-small"><?php esc_html_e( 'None highlighted.', 'hw-trailhead' ); ?></span><?php endif; ?>
						<?php foreach ( $facts as $k => $f ) : ?>
							<label class="th-fact"><input type="checkbox" name="fact[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( in_array( $k, $checked, true ) ); ?>> <span><?php echo esc_html( $f ); ?></span></label>
						<?php endforeach; ?>
					</section>
					<section class="th-card"><h3><?php esc_html_e( 'Search appearance', 'hw-trailhead' ); ?></h3>
						<label class="th-f" for="th-dseo"><?php esc_html_e( 'SEO title', 'hw-trailhead' ); ?><input id="th-dseo" name="seoTitle" class="th-in" value="<?php echo esc_attr( get_post_meta( $p->ID, '_th_seo_title', true ) ); ?>"></label>
						<label class="th-f" for="th-dmeta"><?php esc_html_e( 'Meta description', 'hw-trailhead' ); ?><textarea id="th-dmeta" name="meta" rows="3" class="th-in"><?php echo esc_textarea( get_post_meta( $p->ID, '_th_meta', true ) ); ?></textarea></label>
						<span class="th-muted th-small"><?php esc_html_e( 'Saved into Yoast or Rank Math when either is active.', 'hw-trailhead' ); ?></span>
					</section>
					<button class="th-btn"><?php esc_html_e( 'Save checks and SEO', 'hw-trailhead' ); ?></button>
				</form>
				<?php self::image_panel( $i, $p ); ?>
				<section class="th-card hot"><h3><?php esc_html_e( 'Ask for changes', 'hw-trailhead' ); ?></h3>
					<form method="post" action="<?php echo $act; // phpcs:ignore ?>" class="th-stack">
						<?php echo TH_Actions::fields( 'draft_revise', array( 'id' => $i['id'] ) ); // phpcs:ignore ?>
						<label class="screen-reader-text" for="th-dask"><?php esc_html_e( 'What to change', 'hw-trailhead' ); ?></label>
						<textarea id="th-dask" name="ask" rows="3" class="th-in" placeholder="<?php esc_attr_e( 'e.g. Shorten section 2 and make the intro punchier', 'hw-trailhead' ); ?>" required></textarea>
						<button class="th-btn d" <?php disabled( (bool) $w || 'ready' === $i['stage'] ); ?>><?php esc_html_e( 'Send to Claude', 'hw-trailhead' ); ?></button>
					</form>
				</section>
				<section class="th-card"><h3><?php esc_html_e( 'Finish', 'hw-trailhead' ); ?></h3>
					<?php if ( 'ready' === $i['stage'] ) : ?>
						<span class="th-chip ok"><?php esc_html_e( 'Reviewed', 'hw-trailhead' ); ?></span>
						<span class="th-muted th-small"><?php esc_html_e( 'Add images in the editor and publish when you\'re ready.', 'hw-trailhead' ); ?></span>
					<?php else : ?>
						<?php echo self::button( 'reviewed', __( 'Mark as reviewed', 'hw-trailhead' ), array( 'id' => $i['id'] ), 'p' ); // phpcs:ignore ?>
						<span class="th-muted th-small"><?php echo $left ? esc_html( sprintf( /* translators: %d: count */ _n( 'Tick the %d highlighted fact and save first.', 'Tick the %d highlighted facts and save first.', $left, 'hw-trailhead' ), $left ) ) : esc_html__( 'Removes the highlights from the draft. Publishing stays with you.', 'hw-trailhead' ); ?></span>
					<?php endif; ?>
				</section>
			</aside>
		</div>
		<?php
	}

	/**
	 * Image slots with photo search.
	 *
	 * @param array   $i Item.
	 * @param WP_Post $p Draft.
	 * @return void
	 */
	private static function image_panel( $i, $p ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$slots     = TH_Images::slots( $p->post_content );
		$providers = TH_Images::providers();
		$open      = isset( $_GET['slot'] ) ? (int) $_GET['slot'] : -1;
		$prov      = sanitize_key( $_GET['src'] ?? ( isset( $providers['unsplash'] ) ? 'unsplash' : ( $providers ? array_key_first( $providers ) : '' ) ) );
		$q         = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		// phpcs:enable
		echo '<section class="th-card"><h3>' . esc_html__( 'Images', 'hw-trailhead' ) . '</h3>';
		if ( ! $slots ) {
			echo '<span class="th-muted th-small">' . esc_html__( 'No image slots in this draft.', 'hw-trailhead' ) . '</span></section>';
			return;
		}
		if ( ! $providers ) {
			echo '<span class="th-muted th-small">' . esc_html__( 'Add an Unsplash or Pexels API key in Settings → Images and photos are placed automatically when drafts arrive. Until then, add photos in the editor.', 'hw-trailhead' ) . '</span>';
		}
		foreach ( $slots as $k => $slot ) {
			echo '<div class="th-slot"><div class="th-small"><b>' . esc_html( sprintf( /* translators: %d: number */ __( 'Image %d', 'hw-trailhead' ), $k + 1 ) ) . '</b> · ' . esc_html( $slot['desc'] ) . '</div>';
			if ( $slot['filled'] ) {
				echo '<div class="th-row tight"><img class="th-thumb" src="' . esc_url( $slot['src'] ) . '" alt=""><span class="th-muted th-small th-grow">' . esc_html( $slot['credit'] ) . '</span>';
				if ( $providers && $open !== $k ) {
					echo '<a class="th-btn s" href="' . esc_url( self::url( 'drafts', array( 'item' => $i['id'], 'slot' => $k ) ) ) . '">' . esc_html__( 'Swap', 'hw-trailhead' ) . '</a>';
				}
				echo '</div>';
			}
			if ( $providers && ( ! $slot['filled'] || $open === $k ) ) {
				echo '<form method="get" class="th-row tight"><input type="hidden" name="page" value="trailhead"><input type="hidden" name="view" value="drafts"><input type="hidden" name="item" value="' . esc_attr( $i['id'] ) . '"><input type="hidden" name="slot" value="' . esc_attr( $k ) . '">';
				echo '<label class="screen-reader-text" for="th-iq-' . esc_attr( $k ) . '">' . esc_html__( 'Search photos', 'hw-trailhead' ) . '</label><input id="th-iq-' . esc_attr( $k ) . '" class="th-in th-grow" name="q" value="' . esc_attr( $open === $k && $q ? $q : $slot['desc'] ) . '">';
				echo '<label class="screen-reader-text" for="th-is-' . esc_attr( $k ) . '">' . esc_html__( 'Library', 'hw-trailhead' ) . '</label><select id="th-is-' . esc_attr( $k ) . '" name="src" class="th-sel">';
				foreach ( $providers as $pk => $pl ) {
					echo '<option value="' . esc_attr( $pk ) . '"' . selected( $prov, $pk, false ) . '>' . esc_html( $pl ) . '</option>';
				}
				echo '</select><button class="th-btn s">' . esc_html__( 'Search', 'hw-trailhead' ) . '</button></form>';
			}
			if ( $open === $k && $providers ) {
				$res = TH_Images::search( $prov, $q ? $q : $slot['desc'] );
				if ( is_wp_error( $res ) ) {
					echo '<div class="th-notice bad">' . esc_html( $res->get_error_message() ) . '</div>';
				} elseif ( ! $res ) {
					echo '<span class="th-muted th-small">' . esc_html__( 'No photos found. Try fewer words.', 'hw-trailhead' ) . '</span>';
				} else {
					echo '<div class="th-photos">';
					foreach ( $res as $ph ) {
						echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="th-photo">' . TH_Actions::fields( 'image_use', array( 'id' => $i['id'], 'slot' => $k, 'provider' => $prov, 'photo' => $ph['id'] ) ); // phpcs:ignore
						echo '<button title="' . esc_attr__( 'Use this photo', 'hw-trailhead' ) . '"><img src="' . esc_url( $ph['thumb'] ) . '" alt="' . esc_attr( $ph['alt'] ) . '" loading="lazy"></button><span class="th-small th-muted">' . esc_html( $ph['name'] ) . '</span></form>';
					}
					echo '</div><span class="th-muted th-small">' . esc_html__( 'Click a photo to use it here, with its credit.', 'hw-trailhead' ) . '</span>';
				}
			}
			echo '</div>';
		}
		echo '</section>';
	}

	/* ---------------- Refresh ---------------- */

	/**
	 * The schedule.
	 *
	 * @return void
	 */
	private static function view_refresh() {
		// phpcs:disable WordPress.Security.NonceVerification
		$f     = sanitize_key( $_GET['f'] ?? 'due' );
		$tf    = sanitize_key( $_GET['type'] ?? '' );
		$sort  = sanitize_key( $_GET['sort'] ?? 'due' );
		$paged = max( 1, (int) ( $_GET['p'] ?? 1 ) );
		// phpcs:enable
		$rows   = TH_Schedule::rows();
		$types  = TH_Schedule::types();
		$labels = TH_Schedule::labels();
		$today  = TH_Schedule::today();
		$week   = TH_Schedule::add_days( $today, 7 );
		$in     = function ( $r, $k ) use ( $today, $week ) {
			$sched = $r['interval'] && 'none' !== $r['interval'];
			if ( 'due' === $k ) {
				return $sched && $r['due'] && $r['due'] <= $today;
			}
			if ( 'week' === $k ) {
				return $sched && $r['due'] && $r['due'] <= $week;
			}
			if ( 'none' === $k ) {
				return ! $sched;
			}
			return true;
		};
		$right = self::button( 'schedule_setup', get_option( 'th_schedule_setup' ) ? __( 'Schedule new articles', 'hw-trailhead' ) : __( 'Set up the schedule', 'hw-trailhead' ), array(), get_option( 'th_schedule_setup' ) ? '' : 'p' );
		self::head( __( 'Maintain', 'hw-trailhead' ), __( 'Refresh schedule', 'hw-trailhead' ), __( 'Every English article with its Search Console numbers, a suggested type of refresh, its own interval and an optional note. When one is due, open it and ask Claude for a refresh brief.', 'hw-trailhead' ), $right );

		$tot = array( 'n' => 0, 'clicks' => 0, 'impr' => 0, 'recent' => 0 );
		foreach ( $rows as $r ) {
			if ( $r['interval'] && 'none' !== $r['interval'] ) {
				$tot['n']++;
			}
			$tot['clicks'] += $r['clicks'];
			$tot['impr']   += $r['impressions'];
			$tot['recent'] += $r['recent'];
		}
		$m = TH_Data::window_months();
		?>
		<section class="th-grid4">
			<div class="th-card tight"><span class="th-muted th-small"><?php esc_html_e( 'English articles', 'hw-trailhead' ); ?></span><span class="th-stat"><?php echo esc_html( self::n( count( $rows ) ) ); ?></span><span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s on a schedule', 'hw-trailhead' ), self::n( $tot['n'] ) ) ); ?></span></div>
			<div class="th-card tight"><span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %d: months */ __( 'Clicks, %d months', 'hw-trailhead' ), $m ) ); ?></span><span class="th-stat"><?php echo esc_html( self::n( $tot['clicks'] ) ); ?></span></div>
			<div class="th-card tight"><span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %d: months */ __( 'Impressions, %d months', 'hw-trailhead' ), $m ) ); ?></span><span class="th-stat"><?php echo esc_html( self::n( $tot['impr'] ) ); ?></span></div>
			<div class="th-card tight"><span class="th-muted th-small"><?php esc_html_e( 'Clicks, last 90 days', 'hw-trailhead' ); ?></span><span class="th-stat"><?php echo esc_html( self::n( $tot['recent'] ) ); ?></span></div>
		</section>
		<?php
		$base = array( 'f' => $f, 'type' => $tf, 'sort' => $sort );
		echo '<div class="th-row">';
		foreach ( array( 'due' => __( 'Due now', 'hw-trailhead' ), 'week' => __( 'Next 7 days', 'hw-trailhead' ), 'all' => __( 'All', 'hw-trailhead' ), 'none' => __( 'Not scheduled', 'hw-trailhead' ) ) as $k => $l ) {
			$n = count( array_filter( $rows, function ( $r ) use ( $in, $k, $tf ) { return $in( $r, $k ) && ( ! $tf || $r['type'] === $tf ); } ) );
			echo '<a class="th-pill" aria-pressed="' . ( $f === $k ? 'true' : 'false' ) . '" href="' . esc_url( self::url( 'refresh', array_merge( $base, array( 'f' => $k ) ) ) ) . '">' . esc_html( $l ) . ' <span class="th-muted">' . esc_html( number_format_i18n( $n ) ) . '</span></a>';
		}
		echo '</div><div class="th-row">';
		$tl = array( '' => array( __( 'Any refresh', 'hw-trailhead' ) ) ) + $types;
		foreach ( $tl as $k => $t ) {
			$n = count( array_filter( $rows, function ( $r ) use ( $in, $f, $k ) { return $in( $r, $f ) && ( ! $k || $r['type'] === $k ); } ) );
			echo '<a class="th-pill" aria-pressed="' . ( $tf === $k ? 'true' : 'false' ) . '" href="' . esc_url( self::url( 'refresh', array_merge( $base, array( 'type' => $k ) ) ) ) . '">' . esc_html( $t[0] ) . ' <span class="th-muted">' . esc_html( number_format_i18n( $n ) ) . '</span></a>';
		}
		echo '<span class="th-grow"></span><form method="get" class="th-inline"><input type="hidden" name="page" value="trailhead"><input type="hidden" name="view" value="refresh"><input type="hidden" name="f" value="' . esc_attr( $f ) . '"><input type="hidden" name="type" value="' . esc_attr( $tf ) . '"><label class="th-small" for="th-sort">' . esc_html__( 'Sort by', 'hw-trailhead' ) . '</label> <select id="th-sort" name="sort" class="th-sel" data-autosubmit>';
		foreach ( array( 'due' => __( 'Due date', 'hw-trailhead' ), 'recent' => __( 'Clicks, last 90 days', 'hw-trailhead' ), 'clicks' => __( 'Clicks', 'hw-trailhead' ), 'impressions' => __( 'Impressions', 'hw-trailhead' ), 'change' => __( 'Biggest drop', 'hw-trailhead' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $sort, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></form></div>';
		if ( $tf && isset( $types[ $tf ] ) ) {
			echo '<p class="th-muted th-small">' . esc_html( $types[ $tf ][2] ) . '</p>';
		}

		$list = array_filter( $rows, function ( $r ) use ( $in, $f, $tf ) { return $in( $r, $f ) && ( ! $tf || $r['type'] === $tf ); } );
		uasort(
			$list,
			function ( $a, $b ) use ( $sort ) {
				switch ( $sort ) {
					case 'recent':
						return $b['recent'] - $a['recent'];
					case 'clicks':
						return $b['clicks'] - $a['clicks'];
					case 'impressions':
						return $b['impressions'] - $a['impressions'];
					case 'change':
						return ( $b['prior'] - $b['recent'] ) - ( $a['prior'] - $a['recent'] );
				}
				return strcmp( $a['due'] ?: '9999', $b['due'] ?: '9999' ) ?: $b['recent'] - $a['recent'];
			}
		);
		$total = count( $list );
		$per   = 50;
		$list  = array_slice( $list, ( $paged - 1 ) * $per, $per, true );
		if ( ! $list ) {
			echo '<div class="th-empty"><b>' . esc_html__( 'Nothing in this view.', 'hw-trailhead' ) . '</b>' . ( get_option( 'th_schedule_setup' ) ? '' : '<span>' . esc_html__( 'Set up the schedule to give each article an interval and a due date.', 'hw-trailhead' ) . '</span>' ) . '</div>';
			return;
		}
		?>
		<div class="th-tbl"><table class="th-big">
			<thead><tr><th><?php esc_html_e( 'Article', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Refresh type', 'hw-trailhead' ); ?></th><th><?php echo esc_html( sprintf( /* translators: %d: months */ __( 'Clicks (%dm)', 'hw-trailhead' ), $m ) ); ?></th><th><?php esc_html_e( 'Last 90 days', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Impressions', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'CTR', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Position', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Every', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Due', 'hw-trailhead' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Action', 'hw-trailhead' ); ?></span></th></tr></thead>
			<tbody>
			<?php foreach ( $list as $id => $r ) : ?>
				<?php $change = $r['prior'] ? (int) round( ( $r['recent'] - $r['prior'] ) / $r['prior'] * 100 ) : null; ?>
				<tr>
					<td class="th-wide"><a href="<?php echo esc_url( self::url( 'refresh_item', array( 'post' => $id ) ) ); ?>"><b><?php echo esc_html( get_the_title( $id ) ); ?></b></a>
						<div class="th-muted th-small"><?php echo $r['top'] ? esc_html( sprintf( /* translators: 1: search, 2: position */ __( 'Top search: %1$s (#%2$s)', 'hw-trailhead' ), $r['top']['query'], number_format_i18n( $r['top']['position'], 1 ) ) ) : ''; ?><?php echo esc_html( ' · ' . sprintf( /* translators: %s: date */ __( 'updated %s', 'hw-trailhead' ), get_the_modified_date( 'M Y', $id ) ) ); ?></div>
						<?php if ( $r['note'] ) : ?><div class="th-small"><?php echo esc_html( __( 'Note: ', 'hw-trailhead' ) . $r['note'] ); ?></div><?php endif; ?>
						<?php if ( $r['brief'] ) : ?><span class="th-chip ok"><?php esc_html_e( 'Brief ready', 'hw-trailhead' ); ?></span><?php elseif ( $r['task'] ) : ?><span class="th-chip warn"><?php esc_html_e( 'Waiting for Claude', 'hw-trailhead' ); ?></span><?php endif; ?>
					</td>
					<td><span class="th-chip <?php echo esc_attr( $types[ $r['type'] ][1] ); ?>"><?php echo esc_html( $types[ $r['type'] ][0] ); ?></span></td>
					<td class="num"><?php echo esc_html( self::n( $r['clicks'] ) ); ?></td>
					<td class="num"><?php echo esc_html( self::n( $r['recent'] ) ); ?><?php if ( null !== $change ) : ?> <span class="<?php echo $change < 0 ? 'th-down' : 'th-up'; ?>"><?php echo esc_html( ( $change > 0 ? '+' : '' ) . $change . '%' ); ?></span><?php endif; ?></td>
					<td class="num"><?php echo esc_html( self::n( $r['impressions'] ) ); ?></td>
					<td class="num"><?php echo null !== $r['ctr'] ? esc_html( number_format_i18n( $r['ctr'], 1 ) . '%' ) : '–'; ?></td>
					<td class="num"><?php echo $r['position'] ? esc_html( number_format_i18n( $r['position'], 1 ) ) : '–'; ?></td>
					<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php echo TH_Actions::fields( 'refresh_save', array( 'post' => $id, 'return' => 'refresh' ) ); // phpcs:ignore ?><label class="screen-reader-text" for="th-iv-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Interval', 'hw-trailhead' ); ?></label><select id="th-iv-<?php echo esc_attr( $id ); ?>" name="interval" class="th-sel" data-autosubmit><?php foreach ( $labels as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['interval'] ?: 'none', $k, false ) . '>' . esc_html( $l ) . '</option>'; } ?></select></form></td>
					<td class="num"><?php
					if ( ! $r['interval'] || 'none' === $r['interval'] ) {
						echo '–';
					} elseif ( $r['due'] < $today ) {
						echo '<span class="th-chip bad">' . esc_html( sprintf( /* translators: %d: days */ __( '%dd overdue', 'hw-trailhead' ), ( strtotime( $today ) - strtotime( $r['due'] ) ) / DAY_IN_SECONDS ) ) . '</span>';
					} elseif ( $r['due'] === $today ) {
						echo '<span class="th-chip warn">' . esc_html__( 'Today', 'hw-trailhead' ) . '</span>';
					} else {
						echo esc_html( self::d( $r['due'] ) );
					}
					?></td>
					<td><a class="th-btn s <?php echo $r['due'] && $r['due'] <= $today && 'none' !== $r['interval'] ? 'p' : ''; ?>" href="<?php echo esc_url( self::url( 'refresh_item', array( 'post' => $id ) ) ); ?>"><?php echo esc_html( $r['due'] && $r['due'] <= $today && 'none' !== $r['interval'] ? __( 'Do it now', 'hw-trailhead' ) : __( 'Open', 'hw-trailhead' ) ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="th-row">';
			for ( $p = 1; $p <= $pages; $p++ ) {
				echo '<a class="th-pill" aria-pressed="' . ( $p === $paged ? 'true' : 'false' ) . '" href="' . esc_url( self::url( 'refresh', array_merge( $base, array( 'p' => $p ) ) ) ) . '">' . esc_html( $p ) . '</a>';
			}
			echo '</div>';
		}
		echo '<p class="th-muted th-small">' . esc_html( sprintf( /* translators: %d: months */ __( 'Clicks, impressions, CTR and position are Search Console figures over %d months from Content Health\'s sync; "last 90 days" compares with the 90 days before.', 'hw-trailhead' ), $m ) ) . '</p>';
	}

	/**
	 * One article's refresh.
	 *
	 * @return void
	 */
	private static function view_refresh_item() {
		$id   = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = TH_Schedule::rows();
		if ( ! isset( $rows[ $id ] ) ) {
			echo '<div class="th-empty"><b>' . esc_html__( 'That article isn\'t on the list. It may not be a published English article.', 'hw-trailhead' ) . '</b><a class="th-btn" href="' . esc_url( self::url( 'refresh' ) ) . '">' . esc_html__( 'Back to the schedule', 'hw-trailhead' ) . '</a></div>';
			return;
		}
		$r      = $rows[ $id ];
		$types  = TH_Schedule::types();
		$labels = TH_Schedule::labels();
		$b      = $r['brief'];
		$w      = TH_Tasks::waiting_for( 'post:' . $id );
		$err    = get_post_meta( $id, '_th_error', true );
		$m      = TH_Data::window_months();
		$act    = esc_url( admin_url( 'admin-post.php' ) );
		$change = $r['prior'] ? (int) round( ( $r['recent'] - $r['prior'] ) / $r['prior'] * 100 ) : null;
		?>
		<div class="th-head"><div>
			<p class="th-eb"><a href="<?php echo esc_url( self::url( 'refresh' ) ); ?>"><?php esc_html_e( 'Refresh schedule', 'hw-trailhead' ); ?></a></p>
			<h2 class="th-h1"><?php echo esc_html( get_the_title( $id ) ); ?></h2>
			<div class="th-row tight th-mt"><span class="th-chip <?php echo esc_attr( $types[ $r['type'] ][1] ); ?>"><?php echo esc_html( $types[ $r['type'] ][0] ); ?></span><span class="th-muted th-small"><?php echo esc_html( $types[ $r['type'] ][2] ); ?></span></div>
		</div><div class="th-row tight"><a class="th-btn s" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View live', 'hw-trailhead' ); ?></a><a class="th-btn s" href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php esc_html_e( 'Edit', 'hw-trailhead' ); ?></a></div></div>
		<section class="th-grid4">
			<div class="th-card tight"><span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %d: months */ __( 'Clicks, %d months', 'hw-trailhead' ), $m ) ); ?></span><span class="th-stat"><?php echo esc_html( self::n( $r['clicks'] ) ); ?></span></div>
			<div class="th-card tight"><span class="th-muted th-small"><?php esc_html_e( 'Clicks, last 90 days', 'hw-trailhead' ); ?></span><span class="th-stat"><?php echo esc_html( self::n( $r['recent'] ) ); ?></span><?php if ( null !== $change ) : ?><span class="th-small <?php echo $change < 0 ? 'th-down' : 'th-up'; ?>"><?php echo esc_html( sprintf( /* translators: 1: change, 2: before */ __( '%1$s%% vs %2$s before', 'hw-trailhead' ), ( $change > 0 ? '+' : '' ) . $change, self::n( $r['prior'] ) ) ); ?></span><?php endif; ?></div>
			<div class="th-card tight"><span class="th-muted th-small"><?php esc_html_e( 'Impressions and CTR', 'hw-trailhead' ); ?></span><span class="th-stat"><?php echo esc_html( self::n( $r['impressions'] ) ); ?></span><span class="th-muted th-small"><?php echo null !== $r['ctr'] ? esc_html( number_format_i18n( $r['ctr'], 1 ) . '% ' . __( 'click-through', 'hw-trailhead' ) ) : ''; ?></span></div>
			<div class="th-card tight"><span class="th-muted th-small"><?php esc_html_e( 'Average position', 'hw-trailhead' ); ?></span><span class="th-stat"><?php echo $r['position'] ? esc_html( number_format_i18n( $r['position'], 1 ) ) : '–'; ?></span><span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Last updated %s', 'hw-trailhead' ), get_the_modified_date( '', $id ) ) ); ?></span></div>
		</section>
		<div class="th-cols">
			<div class="th-col-main th-stack">
				<?php if ( $err ) : ?><div class="th-notice bad"><?php echo esc_html( sprintf( /* translators: %s: error */ __( 'Claude couldn\'t do the last request: %s', 'hw-trailhead' ), $err ) ); ?></div><?php endif; ?>
				<?php if ( ! $b ) : ?>
					<section class="th-card hot"><h3><?php esc_html_e( 'Refresh brief', 'hw-trailhead' ); ?></h3>
						<p class="th-p"><?php esc_html_e( 'Claude reads the article, these numbers, its searches and your notes, writes what to change and then writes the updated version for you to review. The live article isn\'t touched.', 'hw-trailhead' ); ?></p>
						<?php echo $w ? self::waiting_chip( $w ) : self::button( 'refresh_queue', __( 'Ask Claude to refresh it', 'hw-trailhead' ), array( 'post' => $id ), 'p' ); // phpcs:ignore ?>
					</section>
				<?php else : ?>
					<section class="th-card"><div class="th-row between"><h3><?php esc_html_e( 'Refresh brief', 'hw-trailhead' ); ?></h3><span class="th-chip <?php echo esc_attr( 'high' === ( $b['priority'] ?? '' ) ? 'bad' : ( 'medium' === ( $b['priority'] ?? '' ) ? 'warn' : '' ) ); ?>"><?php echo esc_html( ucfirst( (string) ( $b['priority'] ?? '' ) ) . ' ' . __( 'priority', 'hw-trailhead' ) ); ?></span></div>
						<p class="th-p"><?php echo esc_html( $b['summary'] ?? '' ); ?></p></section>
					<section class="th-card"><h3><?php esc_html_e( 'What to change', 'hw-trailhead' ); ?></h3>
						<?php foreach ( (array) ( $b['changes'] ?? array() ) as $c ) : ?><div class="th-oli col"><b><?php echo esc_html( $c['what'] ?? '' ); ?></b><span class="th-muted th-small"><?php echo esc_html( $c['why'] ?? '' ); ?></span></div><?php endforeach; ?>
					</section>
					<section class="th-card"><h3><?php esc_html_e( 'Searches to cover better', 'hw-trailhead' ); ?></h3><div class="th-row tight"><?php foreach ( (array) ( $b['queries'] ?? array() ) as $q ) { echo '<span class="th-chip">' . esc_html( $q ) . '</span>'; } ?></div></section>
					<section class="th-card"><h3><?php esc_html_e( 'Search appearance', 'hw-trailhead' ); ?></h3>
						<div class="th-small"><span class="th-muted"><?php esc_html_e( 'SEO title', 'hw-trailhead' ); ?></span><br><?php echo esc_html( ( $b['seoTitle'] ?? '' ) ?: __( 'Keep as it is', 'hw-trailhead' ) ); ?></div>
						<div class="th-small"><span class="th-muted"><?php esc_html_e( 'Meta description', 'hw-trailhead' ); ?></span><br><?php echo esc_html( ( $b['meta'] ?? '' ) ?: __( 'Keep as it is', 'hw-trailhead' ) ); ?></div>
						<?php if ( ! empty( $b['facts'] ) ) : ?><div class="th-notice"><?php echo esc_html( __( 'Re-check: ', 'hw-trailhead' ) . implode( ' · ', (array) $b['facts'] ) ); ?></div><?php endif; ?>
					</section>
					<?php if ( ! empty( $b['sources'] ) ) : ?>
					<section class="th-card"><h3><?php esc_html_e( 'Sources', 'hw-trailhead' ); ?></h3>
						<?php foreach ( (array) $b['sources'] as $src ) { echo '<div class="th-small">' . esc_html( $src['what'] ?? '' ) . ( ! empty( $src['url'] ) ? ' · <a href="' . esc_url( $src['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $src['url'], PHP_URL_HOST ) ) . '</a>' : '' ) . '</div>'; } ?>
					</section>
					<?php endif; ?>
					<?php self::update_panel( $id, $w ); ?>
				<?php endif; ?>
				<section class="th-card"><h3><?php esc_html_e( 'Searches this article appears for', 'hw-trailhead' ); ?></h3>
					<?php $qs = TH_Data::queries( $id, 25 ); ?>
					<?php if ( ! $qs ) : ?><p class="th-muted th-small"><?php esc_html_e( 'No searches recorded in the last 90 days.', 'hw-trailhead' ); ?></p><?php else : ?>
					<div class="th-tbl"><table><thead><tr><th><?php esc_html_e( 'Search', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Clicks', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Impressions', 'hw-trailhead' ); ?></th><th><?php esc_html_e( 'Position', 'hw-trailhead' ); ?></th></tr></thead><tbody>
					<?php foreach ( $qs as $q ) : ?><tr><td><?php echo esc_html( $q['query'] ); ?></td><td class="num"><?php echo esc_html( self::n( $q['clicks'] ) ); ?></td><td class="num"><?php echo esc_html( self::n( $q['impressions'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( (float) $q['position'], 1 ) ); ?></td></tr><?php endforeach; ?>
					</tbody></table></div>
					<p class="th-muted th-small"><?php esc_html_e( 'Last 90 days, by impressions.', 'hw-trailhead' ); ?></p>
					<?php endif; ?>
				</section>
			</div>
			<aside class="th-col-side th-stack">
				<section class="th-card"><h3><?php esc_html_e( 'Schedule', 'hw-trailhead' ); ?></h3>
					<form method="post" action="<?php echo $act; // phpcs:ignore ?>" class="th-stack">
						<?php echo TH_Actions::fields( 'refresh_save', array( 'post' => $id, 'return' => 'refresh_item' ) ); // phpcs:ignore ?>
						<label class="th-f" for="th-int"><?php esc_html_e( 'Every', 'hw-trailhead' ); ?><select id="th-int" name="interval" class="th-in"><?php foreach ( $labels as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['interval'] ?: 'none', $k, false ) . '>' . esc_html( $l ) . '</option>'; } ?></select></label>
						<label class="th-f" for="th-standing"><?php esc_html_e( 'Every refresh, check (optional)', 'hw-trailhead' ); ?><textarea id="th-standing" name="standing" rows="3" class="th-in" placeholder="<?php esc_attr_e( 'Kept for every refresh, e.g. update prices, the hostel list and any new openings', 'hw-trailhead' ); ?>"><?php echo esc_textarea( $r['standing'] ); ?></textarea></label>
						<label class="th-f" for="th-note"><?php esc_html_e( 'Note for this refresh only (optional)', 'hw-trailhead' ); ?><textarea id="th-note" name="note" rows="3" class="th-in" placeholder="<?php esc_attr_e( 'e.g. Check the new metro line', 'hw-trailhead' ); ?>"><?php echo esc_textarea( $r['note'] ); ?></textarea></label>
						<button class="th-btn s"><?php esc_html_e( 'Save', 'hw-trailhead' ); ?></button>
						<span class="th-muted th-small"><?php echo esc_html( ( $r['due'] ? sprintf( /* translators: %s: date */ __( 'Due %s', 'hw-trailhead' ), self::d( $r['due'] ) ) : __( 'Not scheduled', 'hw-trailhead' ) ) . ( $r['last'] ? ' · ' . sprintf( /* translators: %s: date */ __( 'last refreshed %s', 'hw-trailhead' ), self::d( $r['last'] ) ) : '' ) ); ?></span>
					</form>
				</section>
				<?php if ( $b ) : ?>
					<section class="th-card hot"><h3><?php esc_html_e( 'Finish', 'hw-trailhead' ); ?></h3>
						<?php $next = self::d( TH_Schedule::add_days( TH_Schedule::today(), ( TH_Schedule::INTERVALS[ $r['interval'] ] ?? 0 ) ?: 365 ) ); ?>
						<?php if ( '' !== TH_Update::get( $id ) ) : ?>
							<a class="th-btn p" href="<?php echo esc_url( TH_Update::edit_url( $id ) ); ?>"><?php esc_html_e( 'Open in editor', 'hw-trailhead' ); ?></a>
							<span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Opens the live article with the updated text loaded. Nothing changes on the site until you click Update. Then it\'s marked as refreshed and the next refresh is set for %s.', 'hw-trailhead' ), $next ) ); ?></span>
							<?php echo self::button( 'refresh_done', __( 'Mark as refreshed without using it', 'hw-trailhead' ), array( 'post' => $id ), 'link' ); // phpcs:ignore ?>
						<?php else : ?>
							<a class="th-btn" href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php esc_html_e( 'Make the changes in the editor', 'hw-trailhead' ); ?></a>
							<?php echo self::button( 'refresh_done', __( 'Mark as refreshed', 'hw-trailhead' ), array( 'post' => $id ), 'p' ); // phpcs:ignore ?>
							<span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Sets the next refresh for %s.', 'hw-trailhead' ), $next ) ); ?></span>
						<?php endif; ?>
						<?php echo $w ? self::waiting_chip( $w ) : self::button( 'refresh_queue', __( 'Ask Claude again', 'hw-trailhead' ), array( 'post' => $id ), 'link' ); // phpcs:ignore ?>
					</section>
				<?php endif; ?>
			</aside>
		</div>
		<?php
	}

	/**
	 * The updated version Claude wrote: what changed, a preview, and asking
	 * for changes.
	 *
	 * @param int          $id Post ID.
	 * @param WP_Post|null $w  Waiting task.
	 * @return void
	 */
	private static function update_panel( $id, $w ) {
		$html = TH_Update::get( $id );
		echo '<section class="th-card hot"><h3>' . esc_html__( 'Updated version', 'hw-trailhead' ) . '</h3>';
		if ( '' === $html ) {
			echo '<p class="th-p">' . esc_html__( 'This brief came without an updated version. Ask Claude again to get one, or make the changes yourself.', 'hw-trailhead' ) . '</p>';
			echo $w ? self::waiting_chip( $w ) : self::button( 'refresh_queue', __( 'Ask Claude to refresh it', 'hw-trailhead' ), array( 'post' => $id ), 's' ); // phpcs:ignore
			echo '</section>';
			return;
		}
		if ( TH_Update::has_scripts( $id ) ) {
			echo '<div class="th-notice warn">' . esc_html__( 'The live article has embed code (such as a script) that the updated version can\'t carry. After loading it in the editor, check the embeds before clicking Update.', 'hw-trailhead' ) . '</div>';
		}
		$diff = TH_Update::diff( $id );
		echo '<p class="th-muted th-small">' . esc_html__( 'What changed. Removed text is red, new text is green.', 'hw-trailhead' ) . '</p>';
		echo $diff ? '<div class="th-diff">' . $diff . '</div>' : '<p class="th-p">' . esc_html__( 'No text changes.', 'hw-trailhead' ) . '</p>'; // phpcs:ignore
		echo '<details class="th-mt"><summary>' . esc_html__( 'Preview the whole updated article', 'hw-trailhead' ) . '</summary><div class="th-article">' . do_shortcode( wp_kses_post( $html ) ) . '</div></details>'; // phpcs:ignore
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="th-stack th-mt">' . TH_Actions::fields( 'refresh_revise', array( 'post' => $id ) ); // phpcs:ignore
		echo '<label class="th-f" for="th-rask">' . esc_html__( 'Ask for changes', 'hw-trailhead' ) . '<textarea id="th-rask" name="ask" rows="3" class="th-in" placeholder="' . esc_attr__( 'e.g. Keep the original intro. Add the new night bus to the transport section.', 'hw-trailhead' ) . '"></textarea></label>';
		echo $w ? self::waiting_chip( $w ) : '<button class="th-btn s">' . esc_html__( 'Send to Claude', 'hw-trailhead' ) . '</button>'; // phpcs:ignore
		echo '</form></section>';
	}

	/* ---------------- Queue ---------------- */

	/**
	 * The queue and how Claude is set up.
	 *
	 * @return void
	 */
	private static function view_queue() {
		$waiting = TH_Tasks::waiting();
		$done    = get_posts( array( 'post_type' => TH_Tasks::TYPE, 'post_status' => 'private', 'posts_per_page' => 10, 'orderby' => 'modified', 'suppress_filters' => true ) );
		self::head( __( 'Maintain', 'hw-trailhead' ), __( 'Claude queue', 'hw-trailhead' ), __( 'Work waiting for Claude. A scheduled task on your Claude account picks it up, does it with your Ahrefs and WordPress connectors, and puts the result back here.', 'hw-trailhead' ) );
		?>
		<section class="th-card"><h3><?php esc_html_e( 'Waiting', 'hw-trailhead' ); ?></h3>
			<?php if ( ! $waiting ) : ?><p class="th-muted"><?php esc_html_e( 'Nothing waiting.', 'hw-trailhead' ); ?></p><?php endif; ?>
			<?php foreach ( $waiting as $t ) : ?>
				<div class="th-li"><span><b><?php echo esc_html( get_post_meta( $t->ID, '_th_label', true ) ); ?></b><br><span class="th-muted th-small"><?php echo esc_html( sprintf( /* translators: %s: time */ __( 'Queued %s ago', 'hw-trailhead' ), human_time_diff( strtotime( $t->post_date_gmt . ' UTC' ) ) ) ); ?></span></span><?php echo self::button( 'task_cancel', __( 'Remove', 'hw-trailhead' ), array( 'task' => $t->ID ), 's' ); // phpcs:ignore ?></div>
			<?php endforeach; ?>
		</section>
		<section class="th-card"><h3><?php esc_html_e( 'Recently done', 'hw-trailhead' ); ?></h3>
			<?php if ( ! $done ) : ?><p class="th-muted"><?php esc_html_e( 'Nothing yet.', 'hw-trailhead' ); ?></p><?php endif; ?>
			<?php foreach ( $done as $t ) : ?>
				<div class="th-small">✓ <?php echo esc_html( get_post_meta( $t->ID, '_th_label', true ) ); ?> <span class="th-muted"><?php echo esc_html( human_time_diff( strtotime( $t->post_modified_gmt . ' UTC' ) ) . ' ' . __( 'ago', 'hw-trailhead' ) ); ?></span></div>
			<?php endforeach; ?>
		</section>
		<section class="th-card"><h3><?php esc_html_e( 'What Claude is told to do', 'hw-trailhead' ); ?></h3>
			<p class="th-muted th-small"><?php esc_html_e( 'This is the instruction for the scheduled task in Claude. It only ever changes Trailhead tasks: never articles, never publishing.', 'hw-trailhead' ); ?></p>
			<textarea class="th-in" rows="12" readonly id="th-prompt" aria-label="<?php esc_attr_e( 'Claude instruction', 'hw-trailhead' ); ?>"><?php echo esc_textarea( TH_Tasks::claude_prompt() ); ?></textarea>
		</section>
		<?php
	}

	/* ---------------- Settings ---------------- */

	/**
	 * Settings, in tabs.
	 *
	 * @return void
	 */
	private static function view_settings() {
		$s    = TH_Settings::all();
		$tab  = sanitize_key( $_GET['tab'] ?? 'general' ); // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'general'   => __( 'General', 'hw-trailhead' ),
			'playbooks' => __( 'Playbooks', 'hw-trailhead' ),
			'facts'     => __( 'Hostelworld facts and authors', 'hw-trailhead' ),
			'blocks'    => __( 'Blocks and live data', 'hw-trailhead' ),
			'images'    => __( 'Images', 'hw-trailhead' ),
		);
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'general';
		self::head( __( 'Set up once', 'hw-trailhead' ), __( 'Settings', 'hw-trailhead' ), __( 'Everything here is sent to Claude with the work it applies to, so the drafts follow your process.', 'hw-trailhead' ) );
		echo '<div class="th-row">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="th-pill" aria-pressed="' . ( $tab === $k ? 'true' : 'false' ) . '" href="' . esc_url( self::url( 'settings', array( 'tab' => $k ) ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="th-stack">' . TH_Actions::fields( 'settings', array( 'tab' => $tab ) ); // phpcs:ignore

		if ( 'general' === $tab ) {
			?>
			<section class="th-card"><h3><?php esc_html_e( 'Style guide', 'hw-trailhead' ); ?></h3>
				<p class="th-muted th-small"><?php esc_html_e( 'House rules sent with every task.', 'hw-trailhead' ); ?></p>
				<label class="screen-reader-text" for="th-style"><?php esc_html_e( 'Style guide', 'hw-trailhead' ); ?></label>
				<textarea id="th-style" name="style_guide" rows="9" class="th-in"><?php echo esc_textarea( $s['style_guide'] ); ?></textarea>
			</section>
			<section class="th-card"><h3><?php esc_html_e( 'Research', 'hw-trailhead' ); ?></h3>
				<label class="th-f" for="th-comp"><?php esc_html_e( 'Competitors (one domain per line, up to 6)', 'hw-trailhead' ); ?><textarea id="th-comp" name="competitors" rows="4" class="th-in"><?php echo esc_textarea( $s['competitors'] ); ?></textarea></label>
				<div class="th-row stretch">
					<label class="th-f th-grow" for="th-country"><?php esc_html_e( 'Market', 'hw-trailhead' ); ?><select id="th-country" name="country" class="th-in"><?php foreach ( array( 'us' => 'United States', 'gb' => 'United Kingdom', 'au' => 'Australia', 'ca' => 'Canada', 'ie' => 'Ireland' ) as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['country'], $k, false ) . '>' . esc_html( $l ) . '</option>'; } ?></select></label>
					<label class="th-f th-grow" for="th-kw"><?php esc_html_e( 'Ahrefs keywords per seed', 'hw-trailhead' ); ?><input id="th-kw" name="kw_per_seed" type="number" min="10" max="200" class="th-in" value="<?php echo esc_attr( $s['kw_per_seed'] ); ?>"></label>
				</div>
			</section>
			<section class="th-card"><h3><?php esc_html_e( 'Ahrefs', 'hw-trailhead' ); ?></h3>
				<p class="th-muted th-small"><?php esc_html_e( 'With your API key here, WordPress fetches the Ahrefs data for research, competitor gaps and briefs itself and hands it to Claude ready-made, which saves Claude usage. Without it, Claude fetches the data with the Ahrefs connector.', 'hw-trailhead' ); ?></p>
				<label class="th-f" for="th-ahkey"><?php esc_html_e( 'Ahrefs API key', 'hw-trailhead' ); ?><input id="th-ahkey" name="ahrefs_key" type="password" autocomplete="new-password" class="th-in" placeholder="<?php echo $s['ahrefs_key'] ? esc_attr__( 'Saved. Paste a new key to replace it.', 'hw-trailhead' ) : esc_attr__( 'From Ahrefs: Account settings → API keys', 'hw-trailhead' ); ?>"></label>
				<?php if ( $s['ahrefs_key'] ) : ?>
					<label class="th-row tight th-small"><input type="checkbox" name="clear_ahrefs_key" value="1"> <?php esc_html_e( 'Remove the Ahrefs key', 'hw-trailhead' ); ?></label>
					<?php
					$lim = TH_Ahrefs::limits();
					if ( is_wp_error( $lim ) ) {
						echo '<div class="th-notice bad">' . esc_html( $lim->get_error_message() ) . '</div>';
					} else {
						echo '<div class="th-notice ok">' . esc_html( sprintf( /* translators: 1: used, 2: limit, 3: date */ __( 'Connected. This key has used %1$s of %2$s units this month. Resets %3$s.', 'hw-trailhead' ), number_format_i18n( (int) ( $lim['units_usage_api_key'] ?? 0 ) ), null === ( $lim['units_limit_api_key'] ?? null ) ? __( 'your plan\'s', 'hw-trailhead' ) : number_format_i18n( (int) $lim['units_limit_api_key'] ), self::d( (string) ( $lim['usage_reset_date'] ?? '' ) ) ) ) . '</div>';
					}
					$log = (array) get_option( 'th_ahrefs_log', array() );
					if ( $log ) {
						echo '<div class="th-tbl"><table><thead><tr><th>' . esc_html__( 'Run', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'When', 'hw-trailhead' ) . '</th><th>' . esc_html__( 'Units', 'hw-trailhead' ) . '</th></tr></thead><tbody>';
						foreach ( $log as $l ) {
							echo '<tr><td>' . esc_html( $l['label'] ) . ( $l['error'] ? '<br><span class="th-small th-down">' . esc_html( $l['error'] ) . '</span>' : '' ) . '</td><td>' . esc_html( sprintf( /* translators: %s: time */ __( '%s ago', 'hw-trailhead' ), human_time_diff( strtotime( $l['at'] . ' UTC' ) ) ) ) . '</td><td class="num">' . ( null === $l['units'] ? '–' : esc_html( number_format_i18n( $l['units'] ) ) ) . '</td></tr>';
						}
						echo '</tbody></table></div><p class="th-muted th-small">' . esc_html__( 'Repeat lookups within 12 hours use saved answers and cost nothing.', 'hw-trailhead' ) . '</p>';
					}
					?>
				<?php endif; ?>
			</section>
			<section class="th-card"><h3><?php esc_html_e( 'Refresh schedule', 'hw-trailhead' ); ?></h3>
				<label class="th-f" for="th-q"><?php esc_html_e( 'Articles refreshed every quarter', 'hw-trailhead' ); ?><input id="th-q" name="quarterly_count" type="number" min="0" max="5000" class="th-in" value="<?php echo esc_attr( $s['quarterly_count'] ); ?>"></label>
				<label class="th-opt"><input type="checkbox" name="auto_refresh" value="1" <?php checked( (int) $s['auto_refresh'], 1 ); ?>> <?php esc_html_e( 'Every morning, ask Claude to refresh articles that are due', 'hw-trailhead' ); ?></label>
				<label class="th-f" for="th-auto"><?php esc_html_e( 'At most this many a day', 'hw-trailhead' ); ?><input id="th-auto" name="auto_per_day" type="number" min="1" max="50" class="th-in" value="<?php echo esc_attr( $s['auto_per_day'] ); ?>"></label>
				<p class="th-muted th-small"><?php esc_html_e( 'The brief and the updated version are waiting when you open an article. Nothing changes on the site until you open it in the editor and click Update.', 'hw-trailhead' ); ?></p>
			</section>
			<?php
		} elseif ( 'playbooks' === $tab ) {
			echo '<p class="th-muted">' . esc_html__( 'Write down how you do each step, once. Claude gets the playbooks for the step it is working on: research, brief, draft or refresh.', 'hw-trailhead' ) . '</p>';
			foreach ( TH_Settings::steps() as $k => $l ) {
				echo '<section class="th-card"><label class="th-f" for="th-pb-' . esc_attr( $k ) . '">' . esc_html( $l ) . '<textarea id="th-pb-' . esc_attr( $k ) . '" name="pb[' . esc_attr( $k ) . ']" rows="5" class="th-in">' . esc_textarea( $s['playbooks'][ $k ] ?? '' ) . '</textarea></label></section>';
			}
		} elseif ( 'facts' === $tab ) {
			?>
			<section class="th-card"><h3><?php esc_html_e( 'Hostelworld facts', 'hw-trailhead' ); ?></h3>
				<p class="th-muted th-small"><?php esc_html_e( 'The source of truth about Hostelworld and its products: what each is, what it does and its link. Claude uses only this when it mentions Hostelworld.', 'hw-trailhead' ); ?></p>
				<label class="screen-reader-text" for="th-facts"><?php esc_html_e( 'Hostelworld facts', 'hw-trailhead' ); ?></label>
				<textarea id="th-facts" name="facts" rows="12" class="th-in"><?php echo esc_textarea( $s['facts'] ); ?></textarea>
			</section>
			<section class="th-card"><h3><?php esc_html_e( 'Authors and voices', 'hw-trailhead' ); ?></h3>
				<p class="th-muted th-small"><?php esc_html_e( 'One per line: name | how they write and what they cover. You pick one when you start an article.', 'hw-trailhead' ); ?></p>
				<label class="screen-reader-text" for="th-authors"><?php esc_html_e( 'Authors', 'hw-trailhead' ); ?></label>
				<textarea id="th-authors" name="authors" rows="5" class="th-in"><?php echo esc_textarea( $s['authors'] ); ?></textarea>
			</section>
			<?php
		} elseif ( 'blocks' === $tab ) {
			$lib = TH_Library::all();
			echo '<p class="th-muted">' . esc_html__( 'Every shortcode the blog\'s plugins add, found automatically, with real examples from your published articles. Each one with an example is available to Claude, which copies how you already use it. Switch off any you don\'t want in drafts.', 'hw-trailhead' ) . '</p>';
			if ( ! $lib ) {
				echo '<div class="th-empty">' . esc_html__( 'No shortcodes found on this site.', 'hw-trailhead' ) . '</div>';
			}
			foreach ( $lib as $key => $b ) {
				$usable = TH_Library::usable( $b );
				?>
				<section class="th-card">
					<div class="th-row between"><h3><code>[<?php echo esc_html( $b['tag'] ); ?>]</code></h3>
						<div class="th-row tight"><span class="th-chip <?php echo $usable ? 'ok' : ''; ?>"><?php echo esc_html( $usable ? __( 'Available to Claude', 'hw-trailhead' ) : ( $b['off'] ? __( 'Switched off', 'hw-trailhead' ) : __( 'No example yet', 'hw-trailhead' ) ) ); ?></span>
						<label class="th-row tight th-small"><input type="checkbox" name="block_use[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! $b['off'] ); ?>> <?php esc_html_e( 'On', 'hw-trailhead' ); ?></label></div></div>
					<?php if ( $b['found'] ) : ?>
						<div class="th-small"><span class="th-muted"><?php esc_html_e( 'Found in your articles', 'hw-trailhead' ); ?></span><?php foreach ( $b['found'] as $ex ) { echo '<div><code>' . esc_html( $ex ) . '</code></div>'; } ?></div>
					<?php else : ?>
						<p class="th-muted th-small"><?php esc_html_e( 'Not used in any published article yet. Paste a working example below if you want Claude to use it.', 'hw-trailhead' ); ?></p>
					<?php endif; ?>
					<label class="th-f" for="th-bn-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'When to use it (optional)', 'hw-trailhead' ); ?><input id="th-bn-<?php echo esc_attr( $key ); ?>" name="block_note[<?php echo esc_attr( $key ); ?>]" class="th-in" value="<?php echo esc_attr( $b['note'] ); ?>"></label>
					<label class="th-f" for="th-be-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Your own example (optional)', 'hw-trailhead' ); ?><input id="th-be-<?php echo esc_attr( $key ); ?>" name="block_example[<?php echo esc_attr( $key ); ?>]" class="th-in" value="<?php echo esc_attr( $b['example'] ); ?>" placeholder="[<?php echo esc_attr( $b['tag'] ); ?> …]"></label>
				</section>
				<?php
			}
		} elseif ( 'images' === $tab ) {
			?>
			<section class="th-card"><h3><?php esc_html_e( 'Photo libraries', 'hw-trailhead' ); ?></h3>
				<p class="th-muted th-small"><?php esc_html_e( 'With a key, every image slot in a draft gets a search box. Unsplash photos stay on Unsplash\'s servers and are credited, as Unsplash requires. Pexels photos are copied into the Media Library and credited.', 'hw-trailhead' ); ?></p>
				<label class="th-f" for="th-unsplash"><?php esc_html_e( 'Unsplash access key', 'hw-trailhead' ); ?><input id="th-unsplash" name="unsplash_key" type="password" autocomplete="new-password" class="th-in" placeholder="<?php echo $s['unsplash_key'] ? esc_attr__( 'Saved. Paste a new key to replace it.', 'hw-trailhead' ) : esc_attr__( 'From unsplash.com/developers', 'hw-trailhead' ); ?>"></label>
				<?php if ( $s['unsplash_key'] ) : ?><label class="th-row tight th-small"><input type="checkbox" name="clear_unsplash_key" value="1"> <?php esc_html_e( 'Remove the Unsplash key', 'hw-trailhead' ); ?></label><?php endif; ?>
				<label class="th-f" for="th-pexels"><?php esc_html_e( 'Pexels API key', 'hw-trailhead' ); ?><input id="th-pexels" name="pexels_key" type="password" autocomplete="new-password" class="th-in" placeholder="<?php echo $s['pexels_key'] ? esc_attr__( 'Saved. Paste a new key to replace it.', 'hw-trailhead' ) : esc_attr__( 'From pexels.com/api', 'hw-trailhead' ); ?>"></label>
				<?php if ( $s['pexels_key'] ) : ?><label class="th-row tight th-small"><input type="checkbox" name="clear_pexels_key" value="1"> <?php esc_html_e( 'Remove the Pexels key', 'hw-trailhead' ); ?></label><?php endif; ?>
			</section>
			<?php
		}
		echo '<div><button class="th-btn p">' . esc_html__( 'Save settings', 'hw-trailhead' ) . '</button></div></form>';
	}
}
