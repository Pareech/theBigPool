<link rel='stylesheet' type='text/css' href='../css/base.css' />
<link rel='stylesheet' type='text/css' href='../css/navbar.css' />

<!-- Navigation Bar for all pages, except main page -->

<div class='navbar' style="padding-left: 25px;">
  <?php include_once __DIR__ . '/auth_check.php'; ?>

  <!-- DESKTOP: GM Info dropdown with submenus -->
  <div class="dropdown">
    <button style="color:#FFFF00" class="dropbtn">GM<br>Info
      <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <div class="submenu">
        <a href="#">Roster</a>
        <div class="submenu-content">
          <?php
          $get_gmNames = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name");
          foreach ($get_gmNames as $row) {
            $gmName = htmlspecialchars($row['gm_name']);
            echo "<a href='../gm_listings/gm_info.php?gm={$gmName}'>{$gmName}</a>\n";
          }
          ?>
        </div>
      </div>
      <div class="submenu">
        <a href="#">Daily Pts</a>
        <div class="submenu-content">
          <?php
          $get_gmNames3 = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name");
          foreach ($get_gmNames3 as $row) {
            $gmName = htmlspecialchars($row['gm_name']);
            echo "<a href='../gm_listings/gm_daily.php?gm={$gmName}'>{$gmName}</a>\n";
          }
          ?>
        </div>
      </div>
      <a style="color:#006600; font-weight:bold;" href='../draft_day/monies_remaining.php'>Remaining Budgets</a>
    </div>
  </div>

  <div class="dropdown">
    <button style="color:#FFFF00" class="dropbtn">Pool<br>Info
      <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <a class="rules" href='../misc_files/pool_rules.pdf'>RHCP Rules</a>
      <a href='../draft_setup/draft_info.php'>Key Dates and Numbers</a>
      <a href='../misc_files/choosing_waivers.pdf'>How to Make a Waiver Bid</a>
      <hr style="margin: 4px 0; border: 0; border-top: 1px solid #888888ff;">
      <?php
      $check_lotto_done = $pdo->query("SELECT lotto_date, lotto_time FROM base_numbers")->fetch(PDO::FETCH_ASSOC);
      if ($check_lotto_done['lotto_date'] !== null && $check_lotto_done['lotto_time'] !== null):
      ?>
        <a href="../draft_lottery/draft_lottery_4_review.php">Lottery Order Results</a>
      <?php endif; ?>
      <a href="../draft_lottery/draft_simulation.php">Validate Draft Lottery Integrity</a>
      <a href="../misc_files/Lottery_Draw_Sept_2_2025.mp4">Draft Lottery Video</a>
    </div>
  </div>

  <div class="dropdown">
    <button style="color:#FFFF00" class="dropbtn">Entry<br>Draft
      <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <a style="color:red" href='../draft_state/draft_order.php'><strong>The Draft Order</strong></a>
      <hr style="margin: 4px 0; border: 0; border-top: 1px solid #888888ff;">
      <a href='../draft_day/draft_player.php?position=D'>Draft a Defencemen</a>
      <a href='../draft_day/draft_player.php?position=F'>Draft a Forward</a>
      <a href='../draft_day/draft_player.php?position=G'>Draft a Goalie</a>
    </div>
  </div>

  <!-- Other privileged menus -->
  <?php if ($justMe): ?>
    <div class="dropdown">
      <button style="color:#FFFF00" class="dropbtn">My<br>Notes
        <i class="fa fa-caret-down"></i>
      </button>
      <div class="dropdown-content">
        <a href='../for_me_only/make_draft_notes.php'>Make Drafting Notes</a>
        <a href='../for_me_only/my_draft_notes.php'>Review Notes</a>
      </div>
    </div>

    <div class="dropdown">
      <button style="color:#FFFF00" class="dropbtn">Player<br>Updates
        <i class="fa fa-caret-down"></i>
      </button>
      <div class="dropdown-content">
        <a href='../player_updates/missing_player.php'>Add Missing Player</a>
        <a href='../player_updates/update_player_info.php'>Update Player Info</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="dropdown">
    <button style="color:#FFFF00" class="dropbtn">Pre-Season<br>Setup
      <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <?php if ($justMe): ?>
        <div class="submenu">
          <a href="#">Database Prep</a>
          <div class="submenu-content">
            <a href="../draft_setup/prep_db.php">Clear Databases (Pre-Season Setup)</a>
            <a href="../draft_setup/clear_protect_drop.php">Clear Protection Reset List</a>
            <a href="../draft_setup/season_base_numbers.php">Update Cap / Key Dates</a>
          </div>
        </div>
      <?php endif; ?>
      <div class="submenu">
        <a href="#">Draft Lottery</a>
        <div class="submenu-content">
          <?php if ($justMe): ?>
            <a href="../draft_lottery/draft_lottery_1_setDraftOrder.php">Enter Draft Odds Order (11 to 1)</a>
          <?php endif; ?>
          <a href="../draft_lottery/draft_lottery_4_review.php">Draft Order Results</a>
        </div>
      </div>
      <div class="submenu">
        <a href="#">Roster Moves</a>
        <div class="submenu-content">
          <a href="../draft_setup/protect_players.php">Set Protection List</a>
          <a href="../draft_setup/franchise_player_select.php">Tag Franchise Player</a>
          <a href="../trades/trades_completed.php">Completed Trades</a>
          <?php if ($justMe): ?>
            <hr style="margin: 4px 0; border: 0; border-top: 1px solid #888888ff;">
            <a href="../trades/trade_players_gm_select.php">Trade Players</a>
            <a href="../gm_listings/gm_selection.php?doing=franchise_drop">Drop a GM's Franchise Player</a>
            <a href="../gm_listings/gm_selection.php?doing=drop">Drop Player(s) from a GM's Roster</a>
            <a href="../draft_setup/multi_franchise_drop.php">Remove Expired Franchise (All GMs)</a>
            <a href="../draft_setup/remove_waiver_monies.php">Remove Waiver Monies (All GMs)</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Waiver Drafts -->
  <div class="dropdown">
    <button style="color:#FFFF00" class="dropbtn">Waiver<br>Drafts
      <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <a href='../waivers/waiver_picking.php'>Make Waiver Pick(s)</a>
      <a href='../waivers/waiver_gm_picks.php'>Show My Waiver Selections</a>
      <a href='../waivers/waiver_alter_picks.php'>Modify Waiver Choices</a>
      <hr style="margin: 4px 0; border: 0; border-top: 2px solid #888888ff;">
      <a href='../waivers/waiver_all_picks.php'>Display all Waiver Picks</a>
      <a href='../waivers/waiver_results.php'>Show Waiver Winners</a>
    </div>
  </div>

  <div class="dropdown">
    <button style="color:#FFFF00" class="dropbtn">Player<br>Search
      <i class="fa fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <a><?php include __DIR__ .  '/../find_player/player_search.php' ?></a>
      <a href='../find_player/search_criteria_select.php'>By Position & Salary</a>
    </div>
  </div>

  <a style="color:#B9FEB9" href='../player_scoring/leaderboard.php'>RAW<br>Standings</a>
  <a class="log_out" href='../drop_the_puck/final_buzzer.php'>Log<br>Out</a>

  <?php
  $environment = '';
  if ($dbname !== 'RAW_HockeyPool_prod') {
    $environment = ($dbname === 'RAW_HockeyPool_dev') ? 'DEV' : 'PreProd';
  }
  ?>

  <?php if ($environment): ?>
    <a
      class="env-label"><br><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></a>
  <?php endif; ?>

  <div class="topnav-right">
    <a style="color:#99FF99" href="https://puckpedia.com" target="_blank" rel="noopener noreferrer">Link
      to<br>PuckPedia</a>
  </div>

</div>

<!-- MOBILE: Hamburger button + menu -->
<button class="hamburger" onclick="toggleMobileMenu()">&#9776; Menu</button>

<div class="mobile-menu" id="mobileMenu">

  <!-- GM Info -->
  <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileGMInfo')">GM Info</button>
  <div class="mobile-dropdown-content" id="mobileGMInfo">
    <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileGMTeam')" style="padding-left:20px;">Roster</button>
    <div class="mobile-dropdown-content" id="mobileGMTeam">
      <?php
      $get_gmNames2 = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name");
      foreach ($get_gmNames2 as $row) {
        $gmName = htmlspecialchars($row['gm_name']);
        echo "<a href='../gm_listings/gm_info.php?gm={$gmName}' style='padding-left:30px;'>{$gmName}</a>\n";
      }
      ?>
    </div>
    <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileDailyPts')" style="padding-left:20px;">Daily
      Pts</button>
    <div class="mobile-dropdown-content" id="mobileDailyPts">
      <?php
      $get_gmNames4 = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name");
      foreach ($get_gmNames4 as $row) {
        $gmName = htmlspecialchars($row['gm_name']);
        echo "<a href='../gm_listings/gm_daily.php?gm={$gmName}' style='padding-left:30px;'>{$gmName}</a>\n";
      }
      ?>
    </div>
    <a style="color:#00ff00; font-weight:bold; padding-left:20px;" href='../draft_day/monies_remaining.php'>Remaining
      Budgets</a>
  </div>

  <!-- Pool Info -->
  <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobilePoolInfo')">Pool Info</button>
  <div class="mobile-dropdown-content" id="mobilePoolInfo">
    <a class="rules" href='../misc_files/pool_rules.pdf'>RHCP Rules</a>
    <a href='../draft_setup/draft_info.php'>Key Dates and Numbers</a>
    <a href='../misc_files/choosing_waivers.pdf'>How to Make a Waiver Bid</a>
    <hr style="margin: 4px 0; border: 0; border-top: 1px solid #888888ff;">
    <?php if ($check_lotto_done['lotto_date'] !== null && $check_lotto_done['lotto_time'] !== null): ?>
      <a href="../draft_lottery/draft_lottery_4_review.php">Lottery Order Results</a>
    <?php endif; ?>
    <a href="../draft_lottery/draft_simulation.php">Validate Draft Lottery Integrity</a>
    <a href="../misc_files/Lottery_Draw_Sept_2_2025.mp4">Draft Lottery Video</a>
  </div>

  <!-- Entry Draft -->
  <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileEntryDraft')">Entry Draft</button>
  <div class="mobile-dropdown-content" id="mobileEntryDraft">
    <a style="color:red" href='../draft_state/draft_order.php'><strong>The Draft Order</strong></a>
    <hr style="margin: 4px 0; border: 0; border-top: 1px solid #888888ff;">
    <a href='../draft_day/draft_player.php?position=D'>Draft a Defencemen</a>
    <a href='../draft_day/draft_player.php?position=F'>Draft a Forward</a>
    <a href='../draft_day/draft_player.php?position=G'>Draft a Goalie</a>
  </div>

  <!-- My Notes (admin only) -->
  <?php if ($justMe): ?>
    <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileMyNotes')">My Notes</button>
    <div class="mobile-dropdown-content" id="mobileMyNotes">
      <a href='../for_me_only/make_draft_notes.php'>Make Drafting Notes</a>
      <a href='../for_me_only/my_draft_notes.php'>Review Notes</a>
    </div>

    <!-- Player Updates (admin only) -->
    <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobilePlayerUpdates')">Player Updates</button>
    <div class="mobile-dropdown-content" id="mobilePlayerUpdates">
      <a href='../player_updates/missing_player.php'>Add Missing Player</a>
      <a href='../player_updates/update_player_info.php'>Update Player Info</a>
    </div>
  <?php endif; ?>

  <!-- Pre-Season Setup -->
  <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobilePreSeason')">Pre-Season Setup</button>
  <div class="mobile-dropdown-content" id="mobilePreSeason">
    <?php if ($justMe): ?>
      <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileDbPrep')"
        style="padding-left:20px;">Database Prep</button>
      <div class="mobile-dropdown-content" id="mobileDbPrep">
        <a href="../draft_setup/prep_db.php" style="padding-left:30px;">Clear Databases (Pre-Season Setup)</a>
        <a href="../draft_setup/clear_protect_drop.php" style="padding-left:30px;">Clear Protection Reset List</a>
        <a href="../draft_setup/season_base_numbers.php" style="padding-left:30px;">Update Cap / Key Dates</a>
      </div>
    <?php endif; ?>
    <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileDraftLottery')"
      style="padding-left:20px;">Draft Lottery</button>
    <div class="mobile-dropdown-content" id="mobileDraftLottery">
      <?php if ($justMe): ?>
        <a href="../draft_lottery/draft_lottery_1_setDraftOrder.php" style="padding-left:30px;">Enter Draft Odds Order (11 to 1)</a>
      <?php endif; ?>
      <a href="../draft_lottery/draft_lottery_4_review.php" style="padding-left:30px;">Draft Order Results</a>
    </div>
    <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileRosterMovesAdmin')"
      style="padding-left:20px;">Roster Moves</button>
    <div class="mobile-dropdown-content" id="mobileRosterMovesAdmin">
      <a href="../draft_setup/protect_players.php" style="padding-left:30px;">Set Protection List</a>
      <a href="../draft_setup/franchise_player_select.php" style="padding-left:30px;">Tag Franchise Player</a>
      <a href="../trades/trades_completed.php" style="padding-left:30px;">Completed Trades</a>
      <?php if ($justMe): ?>
        <hr style="margin: 4px 0; border: 0; border-top: 1px solid #888888ff;">
        <a href="../trades/trade_players_gm_select.php" style="padding-left:30px;">Trade Players</a>
        <a href="../gm_listings/gm_selection.php?doing=franchise_drop" style="padding-left:30px;">Drop a GM's Franchise Player</a>
        <a href="../gm_listings/gm_selection.php?doing=drop" style="padding-left:30px;">Drop Player(s) from a GM's Roster</a>
        <a href="../draft_setup/multi_franchise_drop.php" style="padding-left:30px;">Remove Expired Franchise (All GMs)</a>
        <a href="../draft_setup/remove_waiver_monies.php" style="padding-left:30px;">Remove Waiver Monies (All GMs)</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Waiver Drafts -->
  <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobileWaivers')">Waiver Drafts</button>
  <div class="mobile-dropdown-content" id="mobileWaivers">
    <a href='../waivers/waiver_picking.php'>Make Waiver Pick(s)</a>
    <a href='../waivers/waiver_gm_picks.php'>Show My Waiver Selections</a>
    <a href='../waivers/waiver_alter_picks.php'>Modify Waiver Choices</a>
    <hr>
    <a href='../waivers/waiver_all_picks.php'>Display all Waiver Picks</a>
    <a href='../waivers/waiver_results.php'>Show Waiver Winners</a>
  </div>

  <!-- Player Search -->
  <button class="mobile-dropbtn" onclick="toggleMobileDropdown('mobilePlayerSearch')">Player Search</button>
  <div class="mobile-dropdown-content" id="mobilePlayerSearch">
    <?php include __DIR__ .  '/../find_player/player_search.php' ?>
    <a href='../find_player/search_criteria_select.php'>By Position & Salary</a>
  </div>

  <a style="color:#B9FEB9" href='../player_scoring/leaderboard.php'>RAW Standings</a>
  <a class="log_out" href='../drop_the_puck/final_buzzer.php'>Log Out</a>
  <a style="color:#99FF99" href="https://puckpedia.com" target="_blank" rel="noopener noreferrer">Link to PuckPedia</a>

  <?php if ($environment): ?>
    <a
      class="env-label"><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></a>
  <?php endif; ?>

</div>

<script>
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('open');
  }

  function toggleMobileDropdown(id) {
    const content = document.getElementById(id);
    content.classList.toggle('open');
  }
</script>