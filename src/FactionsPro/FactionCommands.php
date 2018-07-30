<?php
namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;
class FactionCommands {

  // ASCII Map
  CONST MAP_WIDTH = 48;
  CONST MAP_HEIGHT = 8;
  CONST MAP_HEIGHT_FULL = 17;

  CONST MAP_KEY_CHARS = "\\/#?ç¬£$%=&^ABCDEFGHJKLMNOPQRSTUVWXYZÄÖÜÆØÅ1234567890abcdeghjmnopqrsuvwxyÿzäöüæøåâêîûô";
  CONST MAP_KEY_WILDERNESS = TextFormat::GRAY . "-";
  CONST MAP_KEY_SEPARATOR = TextFormat::AQUA . "+";
  CONST MAP_KEY_OVERFLOW = TextFormat::WHITE . "-" . TextFormat::WHITE; # ::MAGIC?
  CONST MAP_OVERFLOW_MESSAGE = self::MAP_KEY_OVERFLOW . ": Muitas facções (>" . 107 . ") no mapa.";

  public $plugin;

  public function __construct(FactionMain $pg) {
    $this->plugin = $pg;
  }

  public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
    if($sender instanceof Player) {
      $player = $sender->getPlayer()->getName();
      if(strtolower($command->getName('f'))) {
        if(empty($args)) {
          $sender->sendMessage($this->plugin->formatMessage("Te rog foloseste /f help pentru o lista cu comenzi"));
          return true;
        }
        if(count($args == 2)) {

          ///////////////////////////////// WAR /////////////////////////////////

          if($args[0] == "war") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Use: /f war <Numele factiunii:tp>"));
              return true;
            }
            if(strtolower($args[1]) == "tp") {
              foreach($this->plugin->wars as $r => $f) {
                $fac = $this->plugin->getPlayerFaction($player);
                if($r == $fac) {
                  $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                  $tper = $this->plugin->war_players[$f][$x];
                  $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                  return;
                }
                if($f == $fac) {
                  $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                  $tper = $this->plugin->war_players[$r][$x];
                  $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                  return;
                }
              }
              $sender->sendMessage("Nu esti intr-o factiune!");
              return true;
            }
            if(!(ctype_alnum($args[1]))) {
              $sender->sendMessage($this->plugin->formatMessage("Aveti posibilitatea sa utilizati numai litere si cifre!"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea nu exista!"));
              return true;
            }
            if(!$this->plugin->isInFaction($sender->getName())) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune!"));
              return true;
            }
            if(!$this->plugin->isLeader($player)){
              $sender->sendMessage($this->plugin->formatMessage("Numai liderul poate porni un razboi!"));
              return true;
            }
            if(!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea nu este inamica $args[1]!"));
              return true;
            } else {
              $factionName = $args[1];
              $sFaction = $this->plugin->getPlayerFaction($player);
              foreach($this->plugin->war_req as $r => $f) {
                if($r == $args[1] && $f == $sFaction) {
                  foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
                    $task = new FactionWar($this->plugin, $r);
                    $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                    $task->setHandler($handler);
                    $p->sendMessage("Razboiul contra $factionName si $sFaction a inceput!");
                    if($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                      $this->plugin->war_players[$sFaction][] = $p->getName();
                    }
                    if($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                      $this->plugin->war_players[$factionName][] = $p->getName();
                    }
                  }
                  $this->plugin->wars[$factionName] = $sFaction;
                  unset($this->plugin->war_req[strtolower($args[1])]);
                  return true;
                }
              }
              $this->plugin->war_req[$sFaction] = $factionName;
              foreach($this->plugin->getServer()->getOnlinePlayers() as $p) {
                if($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                  if($this->plugin->getLeader($factionName) == $p->getName()) {
                    $p->sendMessage("$sFaction quer começar a guerra de, '/f war $sFaction' pentru a ataca!");
                    $sender->sendMessage("Razboi solicitat!");
                    return true;
                  }
                }
              }
              $sender->sendMessage("Liderul factiunii atacate nu este online.");
              return true;
            }
          }

          /////////////////////////////// CREATE ///////////////////////////////

          if($args[0] == "create") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f create <numele factiunii>"));
              return true;
            }
            if(!(ctype_alnum($args[1]))) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste doar litere si cifre!"));
              return true;
            }
            if($this->plugin->isNameBanned($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Acest nume nu este permis."));
              return true;
            }
            if($this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Nume deja folosit"));
              return true;
            }
            if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
              $sender->sendMessage($this->plugin->formatMessage("Ai intrecut limita de caractere!"));
              return true;
            }
            if($this->plugin->isInFaction($sender->getName())) {
              $sender->sendMessage($this->plugin->formatMessage("Deja aparti de o factiune!"));
              return true;
            } else {
              $factionName = $args[1];
              $rank = "Leader";
              $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
              $stmt->bindValue(":player", $player);
              $stmt->bindValue(":faction", $factionName);
              $stmt->bindValue(":rank", $rank);
              $result = $stmt->execute();
              $this->plugin->updateAllies($factionName);
              $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
              $this->plugin->updateTag($sender->getName());
              $sender->sendMessage($this->plugin->formatMessage("Factiune creeata cu succes!", true));
              return true;
            }
          }

          /////////////////////////////// INVITE ///////////////////////////////

          if($args[0] == "invite") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f invite <Numele playerului>"));
              return true;
            }
            if($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea este plina."));
              return true;
            }
            $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
            if(!($invited instanceof Player)) {
              $sender->sendMessage($this->plugin->formatMessage("Acest player nu este conectat!"));
              return true;
            }
            if($this->plugin->isInFaction($invited) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta!"));
              return true;
            }
            if($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
              if(!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))){
                $sender->sendMessage($this->plugin->formatMessage("Nu ai permisiune!"));
                return true;
              }
            }
            if($invited->getName() == $player){

              $sender->sendMessage($this->plugin->formatMessage("Nu te poti invita in propria factiune, lol"));
              return true;
            }

            $factionName = $this->plugin->getPlayerFaction($player);
            $invitedName = $invited->getName();
            $rank = "Member";

            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
            $stmt->bindValue(":player", $invitedName);
            $stmt->bindValue(":faction", $factionName);
            $stmt->bindValue(":invitedby", $sender->getName());
            $stmt->bindValue(":timestamp", time());
            $result = $stmt->execute();
            $sender->sendMessage($this->plugin->formatMessage("$invitedName a fost invitat in Factiune!", true));
            $invited->sendMessage($this->plugin->formatMessage("Ai fost invitat in factiunea  $factionName. Tasteaza '/f accept' pentru a accepta, sau '/f deny' pentru a respinge", true));

          }

          /////////////////////////////// LEADER ///////////////////////////////

          if($args[0] == "leader") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f leader <nume>"));
              return true;
            }
            if(!$this->plugin->isInFaction($sender->getName())) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta!"));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fi lider pentru a face asta"));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Acest player nu face parte din factiune"));
              return true;
            }
            if(!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
              $sender->sendMessage($this->plugin->formatMessage("Playerul nu este online!"));
              return true;
            }
            if($args[1] == $sender->getName()){

              $sender->sendMessage($this->plugin->formatMessage("Nu iti poti transfera tie liderul"));
              return true;
            }
            $factionName = $this->plugin->getPlayerFaction($player);

            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
            $stmt->bindValue(":player", $player);
            $stmt->bindValue(":faction", $factionName);
            $stmt->bindValue(":rank", "Member");
            $result = $stmt->execute();

            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
            $stmt->bindValue(":player", $args[1]);
            $stmt->bindValue(":faction", $factionName);
            $stmt->bindValue(":rank", "Leader");
            $result = $stmt->execute();


            $sender->sendMessage($this->plugin->formatMessage("Nu mai esti lider"));
            $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("Este noul lider al factiunii $factionName", true));
            $this->plugin->updateTag($sender->getName());
            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
          }

          /////////////////////////////// PROMOTE ///////////////////////////////

          if($args[0] == "promote") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f promote <nume>"));
              return true;
            }
            if(!$this->plugin->isInFaction($sender->getName())) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta!"));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderii au acces sa dea rank in factiune"));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Playerul nu este in factiune!"));
              return true;
            }
            if($args[1] == $sender->getName()){
              $sender->sendMessage($this->plugin->formatMessage("El are deja rankul maxim."));
              return true;
            }

            if($this->plugin->isOfficer($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("El detine deja rankul Officer"));
              return true;
            }
            $factionName = $this->plugin->getPlayerFaction($player);
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
            $stmt->bindValue(":player", $args[1]);
            $stmt->bindValue(":faction", $factionName);
            $stmt->bindValue(":rank", "Officer");
            $result = $stmt->execute();
            $player = $this->plugin->getServer()->getPlayerExact($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("$args[1] A fost promovat la Officer!", true));

            if($player instanceof Player) {
              $player->sendMessage($this->plugin->formatMessage("A fost promovat la Officer  $factionName!", true));
              $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
              return true;
            }
          }

          /////////////////////////////// DEMOTE ///////////////////////////////

          if($args[0] == "demote") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f demote <player>"));
              return true;
            }
            if($this->plugin->isInFaction($sender->getName()) == false) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta!!"));
              return true;
            }
            if($this->plugin->isLeader($player) == false) {
              $sender->sendMessage($this->plugin->formatMessage("Acest player este lider!"));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Nu se afla in factiune"));
              return true;
            }

            if($args[1] == $sender->getName()){
              $sender->sendMessage($this->plugin->formatMessage("Esti deja lider, nu te poti demota!"));
              return true;
            }
            if(!$this->plugin->isOfficer($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Acest player este Officer!"));
              return true;
            }
            $factionName = $this->plugin->getPlayerFaction($player);
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
            $stmt->bindValue(":player", $args[1]);
            $stmt->bindValue(":faction", $factionName);
            $stmt->bindValue(":rank", "Member");
            $result = $stmt->execute();
            $player = $this->plugin->getServer()->getPlayerExact($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("$args[1] A fost retrogradat!", true));
            if($player instanceof Player) {
              $player->sendMessage($this->plugin->formatMessage("A fost retrogradat $factionName!", true));
              $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
              return true;
            }
          }

          /////////////////////////////// KICK ///////////////////////////////

          if($args[0] == "kick") {
            if(!isset($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f kick <player>"));
              return true;
            }
            if($this->plugin->isInFaction($sender->getName()) == false) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta!"));
              return true;
            }
            if($this->plugin->isLeader($player) == false) {
              $sender->sendMessage($this->plugin->formatMessage("Nu iti poti da kick singur"));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Esse Jogador não é dessa Facção!"));
              return true;
            }
            if($args[1] == $sender->getName()){
              $sender->sendMessage($this->plugin->formatMessage("Se é besta. Você não pode se expulsar!"));
              return true;
            }
            $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
            $factionName = $this->plugin->getPlayerFaction($player);
            $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
            $sender->sendMessage($this->plugin->formatMessage("A primit kick $args[1]!", true));
            $this->plugin->subtractFactionPower($factionName,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

            if($kicked instanceof Player) {
              $kicked->sendMessage($this->plugin->formatMessage("A primit kick din $factionName",true));
              $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
              return true;
            }
          }

          /////////////////////////////// INFO ///////////////////////////////

          if(strtolower($args[0]) == 'info') {
            if(isset($args[1])) {
              if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
                $sender->sendMessage($this->plugin->formatMessage("Factiunea nu exista"));
                $sender->sendMessage($this->plugin->formatMessage("Verifica daca numele factiunii este corect."));
                return true;
              }
              $faction = $args[1];
              $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
              $array = $result->fetchArray(SQLITE3_ASSOC);
              $power = $this->plugin->getFactionPower($faction);
              $message = $array["message"];
              $leader = $this->plugin->getLeader($faction);
              $numPlayers = $this->plugin->getNumberOfPlayers($faction);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|[Factiune]| : " . TextFormat::GREEN . "$faction".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|(Líder)| : " . TextFormat::YELLOW . "$leader".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|^Playeri^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|&Putere&| : " . TextFormat::RED . "$power" . " STR".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|*Descriere*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
            }
            else {        
              if(!$this->plugin->isInFaction($player)){
                $sender->sendMessage($this->plugin->formatMessage("Você precisa ser de uma Facção para usar este comando!"));
                return true;
              }
              $faction = $this->plugin->getPlayerFaction(($sender->getName()));
              $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
              $array = $result->fetchArray(SQLITE3_ASSOC);
              $power = $this->plugin->getFactionPower($faction);
              $message = $array["message"];
              $leader = $this->plugin->getLeader($faction);
              $numPlayers = $this->plugin->getNumberOfPlayers($faction);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|[Factiune]| : " . TextFormat::GREEN . "$faction".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|(Líder)| : " . TextFormat::YELLOW . "$leader".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|^Playeri^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|&Putere&| : " . TextFormat::RED . "$power" . " STR".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|*Descriere*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message".TextFormat::RESET);
              $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------".TextFormat::RESET);
            }
          }
          if(strtolower($args[0]) == "help") {
            if(!isset($args[1]) || $args[1] == 1) {
              $sender->sendMessage(TextFormat::GOLD . "Pagina 1 din 6" . TextFormat::RED . "\n§a/f about [Informatii despre plugin]\n§e/f map [Claimurile existente]\n§e/f accept [Accepta o cerere in factiune]\n§e/f overclaim [Da claim teritoriilor inamice]\n§e/f claim\n§e/f create <nume>\n§e/f del\n§e/f demote <nume>\n§e/f deny [Respinge o cerere in Factiune]");
              return true;
            }
            if($args[1] == 2) {
              $sender->sendMessage(TextFormat::GOLD . "Pagina 2 din 6" . TextFormat::RED . "\n§e/f home\n§e/f help <pagina>\n§e/f bank \n§e/f info\n§e/f info <factiune>\n§e/f invite <player>\n§e/f kick <player>\n§e/f leader <player>\n§e/f leave");
              return true;
            }
            if($args[1] == 3) {
              $sender->sendMessage(TextFormat::GOLD . "Pagina 3 din 6" . TextFormat::RED . "\n§e/f sethome\n§8/f unclaim\n§8/f unsethome\n§e/f ourmembers - [Membrii + Status]\n§e/f ourofficers - [Officers + Status]\n§e/f ourleader - [Lider + Status]\n§e/f allies [Aliatii factiunii]");
              return true;
            }
            if($args[1] == 4) {
              $sender->sendMessage(TextFormat::GOLD . "Pagina 4 din 6" . TextFormat::RED . "\n§e/f desc\n§e/f promote <player>\n§e/f allywith <factiune>\n§e/f breakalliancewith <factiune>\n\n§e/f allyok [Accepta o alianta]\n§e/f allyno [Refuza o cerere de alianta]\n§e/f allies <factiune> - [Altiatii factiunii selectate]");
              return true;
            }
            if($args[1] == 5){
              $sender->sendMessage(TextFormat::GOLD . "Pagina 5 din 6" . TextFormat::RED . "\n§e/f membersof <Factiune>\n§e/f officersof <Factiune>\n§e/f leaderof <Factiune>\n§e/f say <Mesaj pe chatul factiunii>\n§e/f pf <player>\n§e/f topfactions [Top 10 Factiuni pe server]");
              return true;
            }
            else {
              $sender->sendMessage(TextFormat::GOLD . "Pagina 6 din 6" . TextFormat::RED . "\n§e/f forceunclaim <Factiune> [Sterge claim-urile unei factiuni - OWNERI]\n\n§e/f forcedelete <Factiune> [Sterge o factiune - OWNERI]\n\n§e/f addstrto <Factiune> <Forta> [Pozitiv + Negativ - - OWNERI]\n");
              return true;
            }
          }
        }
        if(count($args == 1)) {

          /////////////////////////////// CLAIM ///////////////////////////////

          if(strtolower($args[0]) == 'claim') {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune pentru a face asta"));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Numai liderul poate folosi aceasta comanda!"));
              return true;
            }

            if($this->plugin->inOwnPlot($sender)) {
              $sender->sendMessage($this->plugin->formatMessage("Deja ai claim aici!"));
              return true;
            }
            $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
            if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){

              $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                $this->plugin->getNumberOfPlayers($faction);
              $sender->sendMessage($this->plugin->formatMessage("Ai nevoie de $needed_players playeri pentru a da claim"));
              return true;
            }
            if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
              $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
              $faction_power = $this->plugin->getFactionPower($faction);
              $sender->sendMessage($this->plugin->formatMessage("Factiunea ta nu detine destula putere pentru a lua acest teritoriu!"));
              $sender->sendMessage($this->plugin->formatMessage("Factiunea are nevoie de $needed_power de putere, si are doar $faction_power putere."));
              return true;
            }

            $x = floor($sender->getX());
            $y = floor($sender->getY());
            $z = floor($sender->getZ());
            if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

              return true;
            }

            $sender->sendMessage($this->plugin->formatMessage("Obtin coordonatele...", true));
            $plot_size = $this->plugin->prefs->get("PlotSize");
            $faction_power = $this->plugin->getFactionPower($faction);
            $sender->sendMessage($this->plugin->formatMessage("Ai dat claim cu succes!", true));

          }
          if(strtolower($args[0]) == 'plotinfo'){
            $x = floor($sender->getX());
            $y = floor($sender->getY());
            $z = floor($sender->getZ());
            if(!$this->plugin->isInPlot($sender)){
              $sender->sendMessage($this->plugin->formatMessage("Acest teren nu este luat, foloseste /f claim pentru a-l lua", true));
              return true;
            }

            $fac = $this->plugin->factionFromPoint($x,$z);
            $power = $this->plugin->getFactionPower($fac);
            $sender->sendMessage($this->plugin->formatMessage("Acest teren a fost luat de $factionName cu $faction_power putere"));
          }
          if(strtolower($args[0]) == 'topfactions'){
            $this->plugin->sendListOfTop10FactionsTo($sender);
          }
          if(strtolower($args[0]) == 'forcedelete') {
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f forcedelete <Factiune>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if(!($sender->isOp())) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti un Operator."));
              return true;
            }
            $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
            $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
            $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
            $sender->sendMessage($this->plugin->formatMessage("Factiunea a fost stearsa cu succces si terenurile au fost sterse", true));
          }
          if(strtolower($args[0]) == 'addstrto') {
            if(!isset($args[1]) or !isset($args[2])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f addstrto <Factiune> <STR>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if(!($sender->isOp())) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti un Operator."));
              return true;
            }
            $this->plugin->addFactionPower($args[1],$args[2]);
            $sender->sendMessage($this->plugin->formatMessage("A fost executata cu succes $args[2] -> $args[1]", true));
          }
          if(strtolower($args[0]) == 'pf'){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Use: /f pf <PlayerName>"));
              return true;
            }
            if(!$this->plugin->isInFaction($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Player-ul selectat nu este intr-o factiune sau nu exista."));
              $sender->sendMessage($this->plugin->formatMessage("Asigura-te ca numele jucatorului este absolut corect."));
              return true;
            }
            $faction = $this->plugin->getPlayerFaction($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-",true));

          }

          if(strtolower($args[0]) == 'overclaim') {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune pentru a folosi aceasta comanda."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderii pot folosi aceasta comanda."));
              return true;
            }
            $faction = $this->plugin->getPlayerFaction($player);
            if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){

              $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                $this->plugin->getNumberOfPlayers($faction);
              $sender->sendMessage($this->plugin->formatMessage("Ai nevoie de $needed_players Playeri pentru a da overclaim"));
              return true;
            }
            if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
              $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
              $faction_power = $this->plugin->getFactionPower($faction);
              $sender->sendMessage($this->plugin->formatMessage("Factiunea nu are destula putere pentru a face asta!"));
              $sender->sendMessage($this->plugin->formatMessage("Factiunea necesita $needed_power putere pentru a face asta!"));
              return true;
            }
            $sender->sendMessage($this->plugin->formatMessage("Obtin coordonatele...", true));
            $x = floor($sender->getX());
            $y = floor($sender->getY());
            $z = floor($sender->getZ());
            if($this->plugin->prefs->get("EnableOverClaim")){
              if($this->plugin->isInPlot($sender)){
                $faction_victim = $this->plugin->factionFromPoint($x,$z);
                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                $faction_ours = $this->plugin->getPlayerFaction($player);
                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                if($this->plugin->inOwnPlot($sender)){
                  $sender->sendMessage($this->plugin->formatMessage("Nu poti face asta pe propriul teritoriu!"));
                  return true;
                } else {
                  if($faction_ours_power < $faction_victim_power){
                    $sender->sendMessage($this->plugin->formatMessage("Nu poti lua teritoriul $faction_victim pentru ca ei sunt mai puternici ca factiunea ta."));
                    return true;
                  } else {
                    $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                    $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                    $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                    $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
                    $sender->sendMessage($this->plugin->formatMessage("Teritoriul factiunii $faction_victim a fost luat de catre factiunea ta!", true));
                    return true;
                  }

                }
              } else {
                $sender->sendMessage($this->plugin->formatMessage("Trebyue sa fii intr-o factiune!"));
                return true;
              }
            } else {
              $sender->sendMessage($this->plugin->formatMessage("Overclaim este dezactivat!"));
              return true;
            }

          }


          /////////////////////////////// UNCLAIM ///////////////////////////////

          if(strtolower($args[0]) == "unclaim") {
            if(!$this->plugin->isInFaction($sender->getName())) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune."));
              return true;
            }
            if(!$this->plugin->isLeader($sender->getName())) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul poate folosi aceasta comanda."));
              return true;
            }
            $faction = $this->plugin->getPlayerFaction($sender->getName());
            $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
            $sender->sendMessage($this->plugin->formatMessage("Ti-ai declaimat teritoriul.", true));
          }

          /////////////////////////////// DESCRIPTION ///////////////////////////////

          if(strtolower($args[0]) == "desc") {
            if($this->plugin->isInFaction($sender->getName()) == false) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune!"));
              return true;
            }
            if($this->plugin->isLeader($player) == false) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul poate folosi aceasta comanda"));
              return true;
            }
            $sender->sendMessage($this->plugin->formatMessage("Introdu descrierea in chat, ei nu o vor vedea", true));
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
            $stmt->bindValue(":player", $sender->getName());
            $stmt->bindValue(":timestamp", time());
            $result = $stmt->execute();
          }
          
          /////////////////////////////// TOP, also by @PrimusLV //////////////////////////

					if(strtolower($args[0]) === "top") {
						$sortBy = isset($args[1]) ? $args[1] : "money";
						switch ($sortBy) {
							case 'money':
								$this->plugin->sendListOfTop10RichestFactionsTo($sender);
								break;
							case "factions":
								$this->plugin->sendListOfTop10FactionsTo($sender);
							default:
								$sender->sendMessage($this->plugin->formatMessage("§eTOP:."));
								break;
						}
						return true;
					}

          /////////////////////////////// ACCEPT ///////////////////////////////

          if(strtolower($args[0]) == "accept") {
            $player = $sender->getName();
            $lowercaseName = ($player);
            $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(empty($array) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti invitat in nici o factiune!"));
              return true;
            }
            $invitedTime = $array["timestamp"];
            $currentTime = time();
            if(($currentTime - $invitedTime) <= 60) { //This should be configurable
              $faction = $array["faction"];
              $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
              $stmt->bindValue(":player", ($player));
              $stmt->bindValue(":faction", $faction);
              $stmt->bindValue(":rank", "Member");
              $result = $stmt->execute();
              $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
              $sender->sendMessage($this->plugin->formatMessage("Ai intrat cu succes in factiunea $faction!", true));
              $this->plugin->addFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
              $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player A intrat in factiune!", true));
              $this->plugin->updateTag($sender->getName());
            } else {
              $sender->sendMessage($this->plugin->formatMessage("Invitatia a expirat!"));
              $this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
            }
          }

          /////////////////////////////// DENY ///////////////////////////////

          if(strtolower($args[0]) == "deny") {
            $player = $sender->getName();
            $lowercaseName = ($player);
            $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(empty($array) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Nu ai primit nici o invitatie intr-o factiune"));
              return true;
            }
            $invitedTime = $array["timestamp"];
            $currentTime = time();
            if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
              $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
              $sender->sendMessage($this->plugin->formatMessage("Convite recusado!", true));
              $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player a respins invitatia!"));
            } else {
              $sender->sendMessage($this->plugin->formatMessage("Invitatie expirata!"));
              $this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
            }
          }

          /////////////////////////////// DELETE ///////////////////////////////

          if(strtolower($args[0]) == "del") {
            if($this->plugin->isInFaction($player) == true) {
              if($this->plugin->isLeader($player)) {
                $faction = $this->plugin->getPlayerFaction($player);
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                $sender->sendMessage($this->plugin->formatMessage("Factiunea a fost stearsa si teritoriile au fost sterse!", true));
                $this->plugin->updateTag($sender->getName());
              } else {
                $sender->sendMessage($this->plugin->formatMessage("Nu esti lider!"));
              }
            } else {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune!"));
            }
          }

          /////////////////////////////// LEAVE ///////////////////////////////

          if(strtolower($args[0] == "leave")) {
            if($this->plugin->isLeader($player) == false) {
              $remove = $sender->getPlayer()->getNameTag();
              $faction = $this->plugin->getPlayerFaction($player);
              $name = $sender->getName();
              $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
              $sender->sendMessage($this->plugin->formatMessage("Ai parasit factiunea $faction", true));

              $this->plugin->subtractFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
              $this->plugin->updateTag($sender->getName());
            } else {
              $sender->sendMessage($this->plugin->formatMessage("Mai intai ofera cuiva /nrankul de lider sau foloseste /f delete!"));
            }
          }

          /////////////////////////////// SETHOME ///////////////////////////////

          if(strtolower($args[0] == "sethome")) {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Nu apartii de nicio factiune."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul poate face asta."));
              return true;
            }
            $factionName = $this->plugin->getPlayerFaction($sender->getName());
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
            $stmt->bindValue(":faction", $factionName);
            $stmt->bindValue(":x", $sender->getX());
            $stmt->bindValue(":y", $sender->getY());
            $stmt->bindValue(":z", $sender->getZ());
            $result = $stmt->execute();
            $sender->sendMessage($this->plugin->formatMessage("Home creat!", true));
          }

          /////////////////////////////// UNSETHOME ///////////////////////////////

          if(strtolower($args[0] == "unsethome")) {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Nu apartii de nicio factiune."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul poate face asta."));
              return true;
            }
            $faction = $this->plugin->getPlayerFaction($sender->getName());
            $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
            $sender->sendMessage($this->plugin->formatMessage("Home-ul factiunii a fost sters!", true));
          }

          /////////////////////////////// HOME ///////////////////////////////

          if(strtolower($args[0] == "home")) {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            $faction = $this->plugin->getPlayerFaction($sender->getName());
            $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(!empty($array)){
              $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $this->plugin->getServer()->getLevelByName("Factions")));
              $sender->sendMessage($this->plugin->formatMessage("Te teleportez la home-ul factiunii...asteapta.", true));
            }
            else{
              $sender->sendMessage($this->plugin->formatMessage("Nu exista un home."));
            }
          }

          /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
          if(strtolower($args[0] == "ourmembers")){
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Member");

          }
          if(strtolower($args[0] == "membersof")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f membersof <Factiune>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Member");

          }
          if(strtolower($args[0] == "ourofficers")){
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune pentru a face asta."));
              return true;
            }
            $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Officer");
          }
          if(strtolower($args[0] == "officersof")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f officersof <Factiune>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Officer");

          }
          if(strtolower($args[0] == "ourleader")){
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune pentru a face asta."));
              return true;
            }
            $this->plugin->getPlayersInFactionByRank($sender,$this->plugin->getPlayerFaction($player),"Leader");
          }
          if(strtolower($args[0] == "leaderof")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Use: /f leaderof <Factiune>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            $this->plugin->getPlayersInFactionByRank($sender,$args[1],"Leader");

          }
          if(strtolower($args[0] == "say")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f say <mesaj>"));
              return true;
            }
            if(!($this->plugin->isInFaction($player))){

              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta"));
              return true;
            }
            $r = count($args);
            $row = array();
            $rank = "";
            $f = $this->plugin->getPlayerFaction($player);

            if($this->plugin->isOfficer($player)){
              $rank = "*";
            } else if($this->plugin->isLeader($player)){
              $rank = "**";
            }
            $message = "-> ";
            for($i=0;$i<$r-1;$i=$i+1){
              $message = $message.$args[$i+1]." ";
            }
            $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
            for($i=0;$resultArr = $result->fetchArray(SQLITE3_ASSOC);$i=$i+1){
              $row[$i]['player'] = $resultArr['player'];
              $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
              if($p instanceof Player){
                $p->sendMessage(TextFormat::ITALIC.TextFormat::RED."<FM>".TextFormat::AQUA." <$rank$f> ".TextFormat::GREEN."<$player> ".": ".TextFormat::RESET);
                $p->sendMessage(TextFormat::ITALIC.TextFormat::DARK_AQUA.$message.TextFormat::RESET);

              }
            }

          }


          ////////////////////////////// ALLY SYSTEM ////////////////////////////////
          if(strtolower($args[0] == "enemywith")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f enemywith <Factiune>"));
              return true;
            }
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderii pot face asta."));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) == $args[1]){
              $sender->sendMessage($this->plugin->formatMessage("Nu poti fi propriul dusman"));
              return true;
            }
            if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea ta este inamicul factiunii  $args[1]!"));
              return true;
            }
            $fac = $this->plugin->getPlayerFaction($player);
            $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

            if(!($leader instanceof Player)){
              $sender->sendMessage($this->plugin->formatMessage("Liderul factiunii atacate este offline."));
              return true;
            }
            $this->plugin->setEnemies($fac, $args[1]);
            $sender->sendMessage($this->plugin->formatMessage("Esti inamicul factiunii $args[1]!",true));
            $leader->sendMessage($this->plugin->formatMessage("Liderul factiunii $faction este inamic.",true));

          }
          if(strtolower($args[0] == "allywith")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f allywith <Factiune>"));
              return true;
            }
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) == $args[1]){
              $sender->sendMessage($this->plugin->formatMessage("Aceasta factiune nu se poate alia."));
              return true;
            }
            if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea lui este aliata $args[1]!"));
              return true;
            }
            $fac = $this->plugin->getPlayerFaction($player);
            $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
            $this->plugin->updateAllies($fac);
            $this->plugin->updateAllies($args[1]);

            if(!($leader instanceof Player)){
              $sender->sendMessage($this->plugin->formatMessage("Liderul factiunii este offline."));
              return true;
            }
            if($this->plugin->getAlliesCount($args[1])>=$this->plugin->getAlliesLimit()){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea aceasta are deja prea multi aliati!",false));
              return true;
            }
            if($this->plugin->getAlliesCount($fac)>=$this->plugin->getAlliesLimit()){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea a atins deja limita maxima de aliati!",false));
              return true;
            }
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
            $stmt->bindValue(":player", $leader->getName());
            $stmt->bindValue(":faction", $args[1]);
            $stmt->bindValue(":requestedby", $sender->getName());
            $stmt->bindValue(":timestamp", time());
            $result = $stmt->execute();
            $sender->sendMessage($this->plugin->formatMessage("Ai solicitat o alianta cu $args[1]!\nAsteapta raspunsul liderului§8!",true));
            $leader->sendMessage($this->plugin->formatMessage("Liderul factiunii $fac a solicitat o alianta!\n /f allyok pentru a acceota /f allyno Pentru a respinge!",true));

          }
          if(strtolower($args[0] == "breakalliancewith")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Use: /f breakalliancewith <Factiune>"));
              return true;
            }
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Nu apartii de nicio factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul factiunii poate face asta."));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) == $args[1]){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea lui nu se poate alia cu tine."));
              return true;
            }
            if(!$this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea ta nu este aliata cu factiunea ta $args[1]!"));
              return true;
            }

            $fac = $this->plugin->getPlayerFaction($player);
            $leader= $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
            $this->plugin->deleteAllies($fac,$args[1]);
            $this->plugin->deleteAllies($args[1],$fac);
            $this->plugin->subtractFactionPower($fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
            $this->plugin->subtractFactionPower($args[1],$this->plugin->prefs->get("PowerGainedPerAlly"));
            $this->plugin->updateAllies($fac);
            $this->plugin->updateAllies($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("Factiunea ta $fac nu mai este aliata  $args[1]!",true));
            if($leader instanceof Player){
              $leader->sendMessage($this->plugin->formatMessage("Liderul factiunii $fac a spart alianta cu factiunea ta $args[1]!",false));
            }


          }
          if(strtolower($args[0] == "forceunclaim")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Use: /f forceunclaim <Factiune>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea solicitata nu exista."));
              return true;
            }
            if(!($sender->isOp())) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti un Operator."));
              return true;
            }
            $sender->sendMessage($this->plugin->formatMessage("Ai sters teritoriile factiuniicu succes $args[1]!"));
            $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");

          }

          if(strtolower($args[0] == "allies")){
            if(!isset($args[1])){
              if(!$this->plugin->isInFaction($player)) {
                $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
                return true;
              }

              $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
              $this->plugin->getAllAllies($sender,$this->plugin->getPlayerFaction($player));
            } else {
              if(!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
                return true;
              }
              $this->plugin->updateAllies($args[1]);
              $this->plugin->getAllAllies($sender,$args[1]);

            }

          }
          if(strtolower($args[0] == "allyok")){
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderii pot face asta."));
              return true;
            }
            $lowercaseName = ($player);
            $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(empty($array) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Nu ai primit nicio cerere de alianta!"));
              return true;
            }
            $allyTime = $array["timestamp"];
            $currentTime = time();
            if(($currentTime - $allyTime) <= 60) { //This should be configurable
              $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
              $sender_fac = $this->plugin->getPlayerFaction($player);
              $this->plugin->setAllies($requested_fac,$sender_fac);
              $this->plugin->setAllies($sender_fac,$requested_fac);
              $this->plugin->addFactionPower($sender_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
              $this->plugin->addFactionPower($requested_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
              $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
              $this->plugin->updateAllies($requested_fac);
              $this->plugin->updateAllies($sender_fac);
              $sender->sendMessage($this->plugin->formatMessage("Factiunea lui este aliata cu $requested_fac!", true));
              $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player din $sender_fac a acceptat alianta!", true));


            } else {
              $sender->sendMessage($this->plugin->formatMessage("Cererea a expirat!"));
              $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
            }

          }
          if(strtolower($args[0]) == "allyno") {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul poate face asta."));
              return true;
            }
            $lowercaseName = ($player);
            $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(empty($array) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Nu ai primit nicio cerere de alianta!"));
              return true;
            }
            $allyTime = $array["timestamp"];
            $currentTime = time();
            if( ($currentTime - $allyTime) <= 60 ) { //This should be configurable
              $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
              $sender_fac = $this->plugin->getPlayerFaction($player);
              $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
              $sender->sendMessage($this->plugin->formatMessage("Factiunea a refuzat cu succes cererea de alianta.", true));
              $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player din $sender_fac a refuzat alianta!"));

            } else {
              $sender->sendMessage($this->plugin->formatMessage("Cererea a expirat!"));
              $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
            }
          }


          /////////////////////////////// ABOUT ///////////////////////////////

          if(strtolower($args[0] == 'about')) {
            $sender->sendMessage(TextFormat::GREEN . "Factions by " . TextFormat::BOLD . "SupremePE");
          }
          /////////////////////////////// MAP, map by Primus (no compass) ////////////////////////////////
          // Coupon for compass: G1wEmEde0mp455

          if(strtolower($args[0] == "map")) {
            $map = $this->getMap($sender, self::MAP_WIDTH, self::MAP_HEIGHT, $sender->getYaw(), $this->plugin->prefs->get("PlotSize"));
            foreach($map as $line) {
              $sender->sendMessage($line);
            }
            return true;
          }

					////////////////////////////// BALANCE, by primus ;) ///////////////////////////////////////

					if(strtolower($args[0]) === "balance" or strtolower($args[0]) === "bank") {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage(" §eNu esti intr-o factiune", false));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($player);
						$balance = $this->plugin->getBalance($faction);
						$sender->sendMessage($this->plugin->formatMessage("§eBanii factiunii: " . TextFormat::GOLD . "$".$balance));
						return true;
					}
					if(strtolower($args[0]) === "wd" or strtolower($args[0]) === "withdraw") {
						if(($e = $this->plugin->getEconomy()) == null) {
							$sender->sendMessage($this->plugin->formatMessage("§eNu ai permisia de a folosi aceasta comanda", true));
							return true;
						}
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Utilizare: §a/f withdraw <suma>"));
							return true;
						}
						if(!is_numeric($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§eSuma trebuie safie un numar", false));
							return true;
						}
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§eNu esti intr-o factiune!", false));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§eNumai liderul poate retrage din banii factiunii!", false));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						if( (($fM = $this->plugin->getBalance($faction)) - ($args[1]) ) < 0 ) {
							$sender->sendMessage($this->plugin->formatMessage("§eFactiunea nu are destui bani!", false));
							return true;
						}
						$this->plugin->takeFromBalance($faction, $args[1]);
						$e->addMoney($sender, $args[1], false, "contul factiunii");
						$sender->sendMessage($this->plugin->formatMessage("$".$args[1]." §7concedido a partir de facção", true));
						return true;
					}
					if(strtolower($args[0]) === "deposit") {
						if(($e = $this->plugin->getEconomy()) === null) {
							$sender->sendMessage($this->plugin->formatMessage("§eNu ai permisia de a folosi aceasta comanda", true));
							return true;
						}
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Utilizare: §a/f depozitare <suma>"));
							return true;
						}
						if(!is_numeric($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§eNumai numere", false));
							return true;
						}
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§eNu esti lider!", false));
							return true;
						}
						if( ( ($e->myMoney($sender)) - ($args[1]) ) < 0 ) {
							$sender->sendMessage($this->plugin->formatMessage("§eNu ai suficienti bani!", false));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						if($e->reduceMoney($sender, $args[1], false, "faction bank account") === \onebone\economyapi\EconomyAPI::RET_SUCCESS) {
							$this->plugin->addToBalance($faction, $args[1]);
							$sender->sendMessage($this->plugin->formatMessage("$".$args[1]." depozitati"));
						}
						return true;
					}

          ////////////////////////////// CHAT ////////////////////////////////
          if(strtolower($args[0]) == "chat" or strtolower($args[0]) == "c"){
            if($this->plugin->isInFaction($player)){
              if(isset($this->plugin->factionChatActive[$player])){
                unset($this->plugin->factionChatActive[$player]);
                $sender->sendMessage($this->plugin->formatMessage("Chatul Factiunii dezactivat!", false));
                return true;
              }
              else{
                $this->plugin->factionChatActive[$player] = 1;
                $sender->sendMessage($this->plugin->formatMessage("§aChatul factiunii activat!", false));
                return true;
              }
            }
            else{
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune."));
              return true;
            }
          }
          if(strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac"){
            if($this->plugin->isInFaction($player)){
              if(isset($this->plugin->allyChatActive[$player])){
                unset($this->plugin->allyChatActive[$player]);
                $sender->sendMessage($this->plugin->formatMessage("Chatul aliatiilor a fost dezactivat!", false));
                return true;
              }
              else{
                $this->plugin->allyChatActive[$player] = 1;
                $sender->sendMessage($this->plugin->formatMessage("§aChatul aliatiilor a fost activat!", false));
                return true;
              }
            }
            else{
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune."));
            }
          }
        }
      }
    }
  }

  public function getMap(Player $observer, int $width, int $height, int $inDegrees, int $size = 16) { // No compass
    $to = (int)sqrt($size);
    $centerPs = new Vector3($observer->x >> $to, 0, $observer->z >> $to);

    $map = [];

    $centerFaction = $this->plugin->factionFromPoint($observer->getFloorX(), $observer->getFloorZ());
    $centerFaction = $centerFaction ? $centerFaction : "Teren liber!\n by @UniQuexD";

    $head = TextFormat::GREEN . " (" . $centerPs->getX() . "," . $centerPs->getZ() . ") " . $centerFaction . " " . TextFormat::WHITE;
    $head = TextFormat::GOLD . str_repeat("_", (($width - strlen($head)) / 2)) . ".[" . $head . TextFormat::GOLD . "]." . str_repeat("_", (($width - strlen($head)) / 2));

    $map[] = $head;

    $halfWidth = $width / 2;
    $halfHeight = $height / 2;
    $width = $halfWidth * 2 + 1;
    $height = $halfHeight * 2 + 1;

    $topLeftPs = new Vector3($centerPs->x + -$halfWidth, 0, $centerPs->z + -$halfHeight);

    // Get the compass
    //$asciiCompass = ASCIICompass::getASCIICompass($inDegrees, TextFormat::RED, TextFormat::GOLD);

    // Make room for the list of names
    $height--;

    /** @var string[] $fList */
    $fList = array();
    $chrIdx = 0;
    $overflown = false;
    $chars = self::MAP_KEY_CHARS;

    // For each row
    for ($dz = 0; $dz < $height; $dz++) {
      // Draw and add that row
      $row = "";
      for ($dx = 0; $dx < $width; $dx++) {
        if ($dx == $halfWidth && $dz == $halfHeight) {
          $row .= (self::MAP_KEY_SEPARATOR);
          continue;
        }

        if (!$overflown && $chrIdx >= strlen(self::MAP_KEY_CHARS)) $overflown = true;
        $herePs = $topLeftPs->add($dx, 0, $dz);
        $hereFaction = $this->plugin->factionFromPoint($herePs->x << $to, $herePs->z << $to);
        $contains = in_array($hereFaction, $fList, true);
        if ($hereFaction === NULL) {
          $row .= self::MAP_KEY_WILDERNESS;
        } elseif (!$contains && $overflown) {
          $row .= self::MAP_KEY_OVERFLOW;
        } else {
          if (!$contains) $fList[$chars{$chrIdx++}] = $hereFaction;
          $fchar = array_search($hereFaction, $fList);
          $row .= $this->getColorForTo($observer, $hereFaction) . $fchar;
        }
      }

      $line = $row; // ... ---------------

      // Add the compass
      //if ($dz == 0) $line = $asciiCompass[0] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));
      //if ($dz == 1) $line = $asciiCompass[1] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));
      //if ($dz == 2) $line = $asciiCompass[2] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));

      $map[] = $line;
    }
    $fRow = "";
    foreach ($fList as $char => $faction) {
      $fRow .= $this->getColorForTo($observer, $faction) . $char . ": " . $faction . " ";
    }
    if ($overflown) $fRow .= self::MAP_OVERFLOW_MESSAGE;
    $fRow = trim($fRow);
    $map[] = $fRow;

    return $map;
  }

  public function getColorForTo(Player $player, $faction) {
    if($this->plugin->getPlayerFaction($player->getName()) === $faction) {
      return TextFormat::GREEN;
    }
    return TextFormat::LIGHT_PURPLE;
  }

}

              $row[$i]['player'] = $resultArr['player'];
              $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
              if($p instanceof Player){
                $p->sendMessage(TextFormat::ITALIC.TextFormat::RED."<FM>".TextFormat::AQUA." <$rank$f> ".TextFormat::GREEN."<$player> ".": ".TextFormat::RESET);
                $p->sendMessage(TextFormat::ITALIC.TextFormat::DARK_AQUA.$message.TextFormat::RESET);

              }
            }

          }


          ////////////////////////////// ALLY SYSTEM ////////////////////////////////
          if(strtolower($args[0] == "enemywith")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f enemywith <Factiune>"));
              return true;
            }
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderii pot face asta."));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) == $args[1]){
              $sender->sendMessage($this->plugin->formatMessage("Nu poti fi propriul dusman"));
              return true;
            }
            if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea ta este inamicul factiunii  $args[1]!"));
              return true;
            }
            $fac = $this->plugin->getPlayerFaction($player);
            $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

            if(!($leader instanceof Player)){
              $sender->sendMessage($this->plugin->formatMessage("Liderul factiunii atacate este offline."));
              return true;
            }
            $this->plugin->setEnemies($fac, $args[1]);
            $sender->sendMessage($this->plugin->formatMessage("Esti inamicul factiunii $args[1]!",true));
            $leader->sendMessage($this->plugin->formatMessage("Liderul factiunii $faction este inamic.",true));

          }
          if(strtolower($args[0] == "allywith")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Foloseste: /f allywith <Factiune>"));
              return true;
            }
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) == $args[1]){
              $sender->sendMessage($this->plugin->formatMessage("Aceasta factiune nu se poate alia."));
              return true;
            }
            if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea lui este aliata $args[1]!"));
              return true;
            }
            $fac = $this->plugin->getPlayerFaction($player);
            $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
            $this->plugin->updateAllies($fac);
            $this->plugin->updateAllies($args[1]);

            if(!($leader instanceof Player)){
              $sender->sendMessage($this->plugin->formatMessage("Liderul factiunii este offline."));
              return true;
            }
            if($this->plugin->getAlliesCount($args[1])>=$this->plugin->getAlliesLimit()){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea aceasta are deja prea multi aliati!",false));
              return true;
            }
            if($this->plugin->getAlliesCount($fac)>=$this->plugin->getAlliesLimit()){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea a atins deja limita maxima de aliati!",false));
              return true;
            }
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
            $stmt->bindValue(":player", $leader->getName());
            $stmt->bindValue(":faction", $args[1]);
            $stmt->bindValue(":requestedby", $sender->getName());
            $stmt->bindValue(":timestamp", time());
            $result = $stmt->execute();
            $sender->sendMessage($this->plugin->formatMessage("Ai solicitat o alianta cu $args[1]!\nAsteapta raspunsul liderului§8!",true));
            $leader->sendMessage($this->plugin->formatMessage("Liderul factiunii $fac a solicitat o alianta!\n /f allyok pentru a acceota /f allyno Pentru a respinge!",true));

          }
          if(strtolower($args[0] == "breakalliancewith")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Use: /f breakalliancewith <Factiune>"));
              return true;
            }
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Nu apartii de nicio factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul factiunii poate face asta."));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
              return true;
            }
            if($this->plugin->getPlayerFaction($player) == $args[1]){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea lui nu se poate alia cu tine."));
              return true;
            }
            if(!$this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Factiunea ta nu este aliata cu factiunea ta $args[1]!"));
              return true;
            }

            $fac = $this->plugin->getPlayerFaction($player);
            $leader= $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
            $this->plugin->deleteAllies($fac,$args[1]);
            $this->plugin->deleteAllies($args[1],$fac);
            $this->plugin->subtractFactionPower($fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
            $this->plugin->subtractFactionPower($args[1],$this->plugin->prefs->get("PowerGainedPerAlly"));
            $this->plugin->updateAllies($fac);
            $this->plugin->updateAllies($args[1]);
            $sender->sendMessage($this->plugin->formatMessage("Factiunea ta $fac nu mai este aliata  $args[1]!",true));
            if($leader instanceof Player){
              $leader->sendMessage($this->plugin->formatMessage("Liderul factiunii $fac a spart alianta cu factiunea ta $args[1]!",false));
            }


          }
          if(strtolower($args[0] == "forceunclaim")){
            if(!isset($args[1])){
              $sender->sendMessage($this->plugin->formatMessage("Use: /f forceunclaim <Factiune>"));
              return true;
            }
            if(!$this->plugin->factionExists($args[1])) {
              $sender->sendMessage($this->plugin->formatMessage("Factiunea solicitata nu exista."));
              return true;
            }
            if(!($sender->isOp())) {
              $sender->sendMessage($this->plugin->formatMessage("Nu esti un Operator."));
              return true;
            }
            $sender->sendMessage($this->plugin->formatMessage("Ai sters teritoriile factiuniicu succes $args[1]!"));
            $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");

          }

          if(strtolower($args[0] == "allies")){
            if(!isset($args[1])){
              if(!$this->plugin->isInFaction($player)) {
                $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
                return true;
              }

              $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
              $this->plugin->getAllAllies($sender,$this->plugin->getPlayerFaction($player));
            } else {
              if(!$this->plugin->factionExists($args[1])) {
                $sender->sendMessage($this->plugin->formatMessage("Factiunea selectata nu exista."));
                return true;
              }
              $this->plugin->updateAllies($args[1]);
              $this->plugin->getAllAllies($sender,$args[1]);

            }

          }
          if(strtolower($args[0] == "allyok")){
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fii intr-o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderii pot face asta."));
              return true;
            }
            $lowercaseName = ($player);
            $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(empty($array) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Nu ai primit nicio cerere de alianta!"));
              return true;
            }
            $allyTime = $array["timestamp"];
            $currentTime = time();
            if(($currentTime - $allyTime) <= 60) { //This should be configurable
              $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
              $sender_fac = $this->plugin->getPlayerFaction($player);
              $this->plugin->setAllies($requested_fac,$sender_fac);
              $this->plugin->setAllies($sender_fac,$requested_fac);
              $this->plugin->addFactionPower($sender_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
              $this->plugin->addFactionPower($requested_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
              $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
              $this->plugin->updateAllies($requested_fac);
              $this->plugin->updateAllies($sender_fac);
              $sender->sendMessage($this->plugin->formatMessage("Factiunea lui este aliata cu $requested_fac!", true));
              $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player din $sender_fac a acceptat alianta!", true));


            } else {
              $sender->sendMessage($this->plugin->formatMessage("Cererea a expirat!"));
              $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
            }

          }
          if(strtolower($args[0]) == "allyno") {
            if(!$this->plugin->isInFaction($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Trebuie sa apartii de o factiune pentru a face asta."));
              return true;
            }
            if(!$this->plugin->isLeader($player)) {
              $sender->sendMessage($this->plugin->formatMessage("Doar liderul poate face asta."));
              return true;
            }
            $lowercaseName = ($player);
            $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
            $array = $result->fetchArray(SQLITE3_ASSOC);
            if(empty($array) == true) {
              $sender->sendMessage($this->plugin->formatMessage("Nu ai primit nicio cerere de alianta!"));
              return true;
            }
            $allyTime = $array["timestamp"];
            $currentTime = time();
            if( ($currentTime - $allyTime) <= 60 ) { //This should be configurable
              $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
              $sender_fac = $this->plugin->getPlayerFaction($player);
              $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
              $sender->sendMessage($this->plugin->formatMessage("Factiunea a refuzat cu succes cererea de alianta.", true));
              $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player din $sender_fac a refuzat alianta!"));

            } else {
              $sender->sendMessage($this->plugin->formatMessage("Cererea a expirat!"));
              $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
            }
          }


          /////////////////////////////// ABOUT ///////////////////////////////

          if(strtolower($args[0] == 'about')) {
            $sender->sendMessage(TextFormat::GREEN . "Factions by " . TextFormat::BOLD . "SupremePE");
          }
          /////////////////////////////// MAP, map by Primus (no compass) ////////////////////////////////
          // Coupon for compass: G1wEmEde0mp455

          if(strtolower($args[0] == "map")) {
            $map = $this->getMap($sender, self::MAP_WIDTH, self::MAP_HEIGHT, $sender->getYaw(), $this->plugin->prefs->get("PlotSize"));
            foreach($map as $line) {
              $sender->sendMessage($line);
            }
            return true;
          }

					////////////////////////////// BALANCE, by primus ;) ///////////////////////////////////////

					if(strtolower($args[0]) === "balance" or strtolower($args[0]) === "bank") {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage(" §6Nu esti intr-o factiune", false));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($player);
						$balance = $this->plugin->getBalance($faction);
						$sender->sendMessage($this->plugin->formatMessage("§6Banii factiunii: " . TextFormat::GOLD . "$".$balance));
						return true;
					}
					if(strtolower($args[0]) === "wd" or strtolower($args[0]) === "withdraw") {
						if(($e = $this->plugin->getEconomy()) == null) {
							$sender->sendMessage($this->plugin->formatMessage("§6Nu ai permisia de a folosi aceasta comanda", true));
							return true;
						}
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Utilizare: §a/f withdraw <suma>"));
							return true;
						}
						if(!is_numeric($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6Suma trebuie safie un numar", false));
							return true;
						}
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6Nu esti intr-o factiune!", false));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6Numai liderul poate retrage din banii factiunii!", false));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						if( (($fM = $this->plugin->getBalance($faction)) - ($args[1]) ) < 0 ) {
							$sender->sendMessage($this->plugin->formatMessage("§6Factiunea nu are destui bani!", false));
							return true;
						}
						$this->plugin->takeFromBalance($faction, $args[1]);
						$e->addMoney($sender, $args[1], false, "contul factiunii");
						$sender->sendMessage($this->plugin->formatMessage("$".$args[1]." §7concedido a partir de facção", true));
						return true;
					}
					if(strtolower($args[0]) === "deposit") {
						if(($e = $this->plugin->getEconomy()) === null) {
							$sender->sendMessage($this->plugin->formatMessage("§6Nu ai permisia de a folosi aceasta comanda", true));
							return true;
						}
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("Utilizare: §a/f depozitare <suma>"));
							return true;
						}
						if(!is_numeric($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§6Numai numere", false));
							return true;
						}
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§6Nu esti lider!", false));
							return true;
						}
						if( ( ($e->myMoney($sender)) - ($args[1]) ) < 0 ) {
							$sender->sendMessage($this->plugin->formatMessage("§6Nu ai suficienti bani!", false));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						if($e->reduceMoney($sender, $args[1], false, "faction bank account") === \onebone\economyapi\EconomyAPI::RET_SUCCESS) {
							$this->plugin->addToBalance($faction, $args[1]);
							$sender->sendMessage($this->plugin->formatMessage("$".$args[1]." depozitati"));
						}
						return true;
					}

          ////////////////////////////// CHAT ////////////////////////////////
          if(strtolower($args[0]) == "chat" or strtolower($args[0]) == "c"){
            if($this->plugin->isInFaction($player)){
              if(isset($this->plugin->factionChatActive[$player])){
                unset($this->plugin->factionChatActive[$player]);
                $sender->sendMessage($this->plugin->formatMessage("Chatul Factiunii dezactivat!", false));
                return true;
              }
              else{
                $this->plugin->factionChatActive[$player] = 1;
                $sender->sendMessage($this->plugin->formatMessage("§aChatul factiunii activat!", false));
                return true;
              }
            }
            else{
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune."));
              return true;
            }
          }
          if(strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac"){
            if($this->plugin->isInFaction($player)){
              if(isset($this->plugin->allyChatActive[$player])){
                unset($this->plugin->allyChatActive[$player]);
                $sender->sendMessage($this->plugin->formatMessage("Chatul aliatiilor a fost dezactivat!", false));
                return true;
              }
              else{
                $this->plugin->allyChatActive[$player] = 1;
                $sender->sendMessage($this->plugin->formatMessage("§aChatul aliatiilor a fost activat!", false));
                return true;
              }
            }
            else{
              $sender->sendMessage($this->plugin->formatMessage("Nu esti intr-o factiune."));
            }
          }
        }
      }
    }
  }

  public function getMap(Player $observer, int $width, int $height, int $inDegrees, int $size = 16) { // No compass
    $to = (int)sqrt($size);
    $centerPs = new Vector3($observer->x >> $to, 0, $observer->z >> $to);

    $map = [];

    $centerFaction = $this->plugin->factionFromPoint($observer->getFloorX(), $observer->getFloorZ());
    $centerFaction = $centerFaction ? $centerFaction : "Teren liber!\n by @UniQuexD";

    $head = TextFormat::GREEN . " (" . $centerPs->getX() . "," . $centerPs->getZ() . ") " . $centerFaction . " " . TextFormat::WHITE;
    $head = TextFormat::GOLD . str_repeat("_", (($width - strlen($head)) / 2)) . ".[" . $head . TextFormat::GOLD . "]." . str_repeat("_", (($width - strlen($head)) / 2));

    $map[] = $head;

    $halfWidth = $width / 2;
    $halfHeight = $height / 2;
    $width = $halfWidth * 2 + 1;
    $height = $halfHeight * 2 + 1;

    $topLeftPs = new Vector3($centerPs->x + -$halfWidth, 0, $centerPs->z + -$halfHeight);

    // Get the compass
    //$asciiCompass = ASCIICompass::getASCIICompass($inDegrees, TextFormat::RED, TextFormat::GOLD);

    // Make room for the list of names
    $height--;

    /** @var string[] $fList */
    $fList = array();
    $chrIdx = 0;
    $overflown = false;
    $chars = self::MAP_KEY_CHARS;

    // For each row
    for ($dz = 0; $dz < $height; $dz++) {
      // Draw and add that row
      $row = "";
      for ($dx = 0; $dx < $width; $dx++) {
        if ($dx == $halfWidth && $dz == $halfHeight) {
          $row .= (self::MAP_KEY_SEPARATOR);
          continue;
        }

        if (!$overflown && $chrIdx >= strlen(self::MAP_KEY_CHARS)) $overflown = true;
        $herePs = $topLeftPs->add($dx, 0, $dz);
        $hereFaction = $this->plugin->factionFromPoint($herePs->x << $to, $herePs->z << $to);
        $contains = in_array($hereFaction, $fList, true);
        if ($hereFaction === NULL) {
          $row .= self::MAP_KEY_WILDERNESS;
        } elseif (!$contains && $overflown) {
          $row .= self::MAP_KEY_OVERFLOW;
        } else {
          if (!$contains) $fList[$chars{$chrIdx++}] = $hereFaction;
          $fchar = array_search($hereFaction, $fList);
          $row .= $this->getColorForTo($observer, $hereFaction) . $fchar;
        }
      }

      $line = $row; // ... ---------------

      // Add the compass
      //if ($dz == 0) $line = $asciiCompass[0] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));
      //if ($dz == 1) $line = $asciiCompass[1] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));
      //if ($dz == 2) $line = $asciiCompass[2] . "" . substr($row, 3 * strlen(Constants::MAP_KEY_SEPARATOR));

      $map[] = $line;
    }
    $fRow = "";
    foreach ($fList as $char => $faction) {
      $fRow .= $this->getColorForTo($observer, $faction) . $char . ": " . $faction . " ";
    }
    if ($overflown) $fRow .= self::MAP_OVERFLOW_MESSAGE;
    $fRow = trim($fRow);
    $map[] = $fRow;

    return $map;
  }

  public function getColorForTo(Player $player, $faction) {
    if($this->plugin->getPlayerFaction($player->getName()) === $faction) {
      return TextFormat::GREEN;
    }
    return TextFormat::LIGHT_PURPLE;
  }

}
