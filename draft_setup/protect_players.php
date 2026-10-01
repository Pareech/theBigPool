<?php
include __DIR__ . '/../misc_files/auth_check.php';
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

// Check if season has started
$protection_list = $pdo->query("SELECT protection_list FROM base_numbers")->fetchColumn();

// Normalize both to date only
$today = new DateTime('today');
$protection_list_dt = (new DateTime($protection_list))->setTime(0, 0);

// Format only for popup
$protection_list_fmt = $protection_list_dt->format("F jS, Y");

if ($today > $protection_list_dt) {
    echo "<script>
        alert('The period to set your protection list has closed.\\n'
            + 'The deadline for submission was $protection_list_fmt.\\n\\n'
            + 'You will need to reach out to the Commish to find out\\n'
            + 'how to proceed.');
        window.location.href='../draft_setup/draft_info.php';
    </script>";
    exit;
}
// End Check if Season has started

if (!empty($_SESSION['gm_name'])) {
    $poolee = ucfirst(strtolower($_SESSION['gm_name']));
}

// Verify if protection list has been submitted start
$check = $pdo->prepare("SELECT count(player) as protected_players FROM salaries WHERE gm = :poolee");
$check->execute(['poolee' => $poolee]);
$protection_done = $check->fetchColumn();

if ($protection_done === 10) {
    echo
    "<script>
        alert('You have already submitted your protection list.');
        window.location.href='../gm_listings/gm_info.php?gm=$poolee';
    </script>";
    exit();
}
// Verify if protection list has been submitted end

// Track previously checked players if form was submitted
$checked_players = $_POST['checkmark'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protection List Players</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/drop.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="grid-container-top">
        <div class="protection_title">
            <h1><?php echo htmlspecialchars($poolee) ?>'s Roster<br>Players to Protection</h1>
        </div>
        <div class="protection_criteria">
            <h2 style='color:#FFA500'><u>Protection Criteria</u></h2>
            <h3>3 Defencemen, 6 Forwards, 1 Goalie</h3>
            <h3 style='color:#FF0000'>Franchise Player is selected by default for the Protection List. Otherwise, ensure you select the player you will franchise.</h3>
        </div>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php
    $result = $pdo->prepare("SELECT player, team, position, current_salary, franchise, salary_retained, waiver_bid 
                             FROM salaries WHERE gm= :gm AND current_salary > 0 ORDER BY position, player;");
    $result->execute(['gm' => $poolee]);
    ?>

    <div class="outer-scroll">
    <form name="display" action="" method="POST">
        <div class="grid-container">
        <div class="button-row">
            <div class="submitButton">
                <button type="submit" id="buttonSet" name="save">Tag Protected Players</button>
            </div>

            <div class="resetButton">
                <button type="button" id="buttonSet" onclick="window.location.href = window.location.pathname;">Refresh the form</button>
            </div>
        </div>

            <div class="playerList">
                <table>
                    <tr>
                        <th>Player</th>
                        <th>Team</th>
                        <th>Position</th>
                        <th>Salary</th>
                        <th>Protect</th>
                    </tr>

                    <?php
                    foreach ($result as $row) {
                        $style = "style='block'";
                        if (is_null($row['franchise'])) {
                            $salary = "$" . number_format($row["current_salary"] ?? 0);
                            $no_check = in_array($row['player'], $checked_players) ? "checked" : "";
                        } else {
                            $salary = '<span style="color:#008000;"><strong>Franchise</strong></span>';
                            $no_check = "checked";
                            $style = "style='display:enabled' onclick='return false;' readonly='readonly'";
                        }

                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row["player"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["team"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["position"]) . "</td>";
                        echo "<td>" . $salary . "</td>";
                        echo "<td><input type='checkbox' id='checkItem' name='checkmark[]' $style $no_check value='" . htmlspecialchars($row['player']) . "'></td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
            </div>
        </div>
    </form>
    </div>

    <?php
    if (isset($_POST['save'])) {
        if (empty($_POST['checkmark'])) {
            echo "<script>
            alert('You did not choose any player to be protected.\\nNo changes were made to your roster.');
        </script>";
        } else {
            $checkbox = $_POST['checkmark'];
            $checkboxSize = count($checkbox);
            $defcount = $fwdcount = $goaliecount = 0;

            // Get Max number for each position to be protected
            $max_position = ("SELECT fwd_protect, def_protect, goalie_protect FROM base_numbers");
            $gm_protected = $pdo->prepare("SELECT position FROM salaries WHERE gm = :gm AND player = :player;");

            foreach ($pdo->query($max_position) as $row) {
                $fwd_protect = $row['fwd_protect'];
                $def_protect = $row['def_protect'];
                $goalie_protect = $row['goalie_protect'];
            }

            for ($i = 0; $i < $checkboxSize; ++$i) {
                $protect_player = $checkbox[$i];
                $gm_protected->execute(['gm' => $poolee, 'player' => $protect_player]);
                while ($row = $gm_protected->fetch()) {
                    $position = $row['position'];
                    if ($position == 'D') {
                        $defcount += 1;
                    } elseif ($position == 'F') {
                        $fwdcount += 1;
                    } else {
                        $goaliecount += 1;
                    }
                }
            }

            if ($defcount != $def_protect) {
                $def_result = ' -  You have protected ' . $defcount . ' defencemen, you must protect ' . $def_protect . '.';
                $x = 1;
            } else {
                $def_result = ' -  Defencemen are fine.';
            }

            if ($fwdcount != $fwd_protect) {
                $fwd_result = ' -  You have protected ' . $fwdcount . ' forwards, you must protect ' . $fwd_protect . '.';
                $x = 1;
            } else {
                $fwd_result = ' -  Forwards are fine.';
            }

            if ($goaliecount != $goalie_protect) {
                $goalie_result = ' -  You have protected ' . $goaliecount . ' goalies, you must protect ' . $goalie_protect . '.';
                $x = 1;
            } else {
                $goalie_result = ' -  Goalies are fine.';
            }

            if (isset($x)) {
                echo "<script>
                alert('There\\'s an issue with your protection list:\\n$def_result\\n$fwd_result\\n$goalie_result');
            </script>";
                // Do NOT redirect — keeps form populated
            } else {

                // Backup roster before protection, to be used if GM wants to reset their protection list
                $backup_roster = $pdo->prepare("UPDATE salaries SET protection_drop = :poolee WHERE gm = :poolee");
                $backup_roster->execute(['poolee' => $poolee]);

                $clear_info = $pdo->prepare("UPDATE salaries
                                             SET drafted = NULL,
                                                 salary_retained = NULL,
                                                 waiver_bid = NULL,
                                                 gm = NULL,
                                                 latest_pick = NULL
                                             WHERE gm = :gm AND franchise is NULL");
                $clear_info->execute(['gm' => $poolee]);

                $did_protect = $pdo->prepare("UPDATE gms
                                              SET team_protected = now()
                                              WHERE gm_name = :gm");
                $did_protect->execute(['gm' => $poolee]);

                for ($i = 0; $i < $checkboxSize; ++$i) {
                    $protect_player = $checkbox[$i];
                    $gm_protected->execute(['gm' => $poolee, 'player' => $protect_player]);
                    while ($row = $gm_protected->fetch()) {
                        $position = $row['position'];
                    }

                    $update = $pdo->prepare("UPDATE salaries 
                                             SET drafted = 'Protected',
                                                 gm = :gm
                                             WHERE player = :player AND franchise IS NULL");
                    $update->execute(['gm' => $poolee,
                                      'player' => $protect_player
                                     ]);

                    include __DIR__ . '/../misc_files/last_db_update.php';
                }

                echo "<script>
                alert('$poolee\\'s roster has been updated.');
                window.location.href='../gm_listings/gm_info.php?gm=$poolee';
             </script>";
            }
        }
    }
    ?>

</body>
</html>