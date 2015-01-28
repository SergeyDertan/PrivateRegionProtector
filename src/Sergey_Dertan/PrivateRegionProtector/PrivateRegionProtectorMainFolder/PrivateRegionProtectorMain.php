<?php
namespace Sergey_Dertan\PrivateRegionProtector\PrivateRegionProtectorMainFolder;

use pocketmine\command\Command as CMD;
use pocketmine\command\CommandSender as CS;
use pocketmine\entity\Entity;
use pocketmine\event\Listener as L;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase as PB;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as F;

/*
* Warning?Ну не error же!
* Данный плагин добавляет привататные территории дял игроков на ваш сервер
* Пишу весь текст выводимый в чат из-за того что частое переключение языка мешает.
* Есть вопрос?Напишите мне в ВК http://vk.com/superator , в GMail superator2018@gmail.com или в Skype Sergey44668800
*/

/*
 * TODO
 * Исправление багов.
*/

/**
 * Class PrivateRegionProtectorMain
 * @package Sergey_Dertan\PrivateRegionProtector\PrivateRegionProtectorMainFolder
 */
class PrivateRegionProtectorMain extends PB implements L
{
    private $config;
    public $pos1 = array(),$pos2 = array(), $areas, $forInfo,$forInfoCheckPerm, $forCF;

    function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents(new PrivateRegionProtectorEventListener($this), $this);
        $this->areas = new Config($this->getDataFolder() . "Areas.yml", Config::YAML, array());
        $this->config = new Config($this->getDataFolder() . "Settings.yml", Config::YAML, array(
            "MaxAreas" => 3,
            "MaxAreaSize" => 30000,
            "DefaultFlags" => array("pvp" => "allow", "build" => "deny", "entry" => "allow", "god-mode" => "deny", "use" => "deny", "send-chat" => "allow", "explode" => "allow", "burn" => "allow", "regain" => "allow", "teleport" => "allow", "mob-damage" => "allow", "sleep" => "allow", "tnt-explode" => "allow", "bucket-use" => "deny", "drop-item" => "allow", "cmd-use" => "allow")));
        $this->getLogger()->info(F::GREEN . "Private Region Protector V" . $this->getDescription()->getVersion() . " by Sergey Dertan load!");
    }

    /**
     * @param CS $s
     * @param CMD $cmd
     * @param string $label
     * @param array $args
     * @return bool|void
     */
    public function onCommand(CS $s, CMD $cmd, $label, array $args)
    {
        switch ($cmd->getName()) {
            case"privatearea":
                if ($s instanceof Player) {
                    if ($s->hasPermission("prp.p")) {
                        $player = $s->getServer()->getPlayer($s->getName());
                        if (isset($args[0])) {
                            if (strtolower($args[0]) == "pos1") {
                                $x1 = $s->getFloorX();
                                $y1 = $s->getFloorY() - 1;
                                $z1 = $s->getFloorZ();
                                $this->pos1[strtolower($s->getName())] = array(0 => $x1, 1 => $y1, 2 => $z1, 'level' => $player->getLevel()->getName());
                                $s->sendMessage(F::YELLOW . "[PRP] First position set (" . $x1 . ", " . $y1 . ", " . $z1 . " )");
                            } elseif (strtolower($args[0]) == "pos2") {
                                $x2 = $s->getFloorX();
                                $y2 = $s->getFloorY() - 1;
                                $z2 = $s->getFloorZ();
                                $this->pos2[strtolower($s->getName())] = array(0 => $x2, 1 => $y2, 2 => $z2, 'level' => $player->getLevel()->getName());
                                $s->sendMessage(F::YELLOW . "[PRP] Second position set (" . $x2 . ", " . $y2 . ", " . $z2 . " )");
                            } elseif (strtolower($args[0]) == "create") {
                                if ((isset($this->pos1[strtolower($s->getName())])) and (isset($this->pos2[strtolower($s->getName())]))) {
                                    if (isset($args[1])) {
                                        if (!$this->areas->exists(strtolower($args[1]))) {
                                            $pos1 = $this->pos1[strtolower($s->getName())];
                                            $pos2 = $this->pos2[strtolower($s->getName())];
                                            if ($pos1["level"] == $pos2["level"]) {
                                                $minX = min($pos1[0], $pos2[0]);
                                                $maxX = max($pos1[0], $pos2[0]);
                                                $minY = min($pos1[1], $pos2[1]);
                                                $maxY = max($pos1[1], $pos2[1]);
                                                $minZ = min($pos1[2], $pos2[2]);
                                                $maxZ = max($pos1[2], $pos2[2]);
                                                $max = array($maxX, $maxY, $maxZ);
                                                $min = array($minX, $minY, $minZ);
                                                if (($maxX - $minX) * ($maxY - $minY) * ($maxZ - $minZ) <= $this->config->get("MaxAreaSize") || $s->hasPermission("prp.doall") || $s->hasPermission("prp.maxareasize")) {
                                                    if (count($this->areas->getAll()) != 0) {
                                                        foreach ($this->areas->getAll() as $name => $info) {
                                                            $f = array();
                                                            foreach ($this->areas->getAll() as $areaname => $areainfo) {
                                                                if (in_array(strtolower($s->getName()), $areainfo["owners"])) {
                                                                    $f[] = $areaname;
                                                                }
                                                            }
                                                            if (count($f) <= $this->config->get("MaxAreas") || $s->hasPermission("prp.doall") || $s->hasPermission("prp.maxareas")) {
                                                                if (!$this->checkCoordinates($info, $minX, $minY, $minZ) and !$this->checkCoordinates($info, $maxX, $maxY, $maxZ)) {
                                                                    $this->areas->set(strtolower($args[1]), array("min" => $min, "max" => $max, "owners" => array(strtolower($s->getName())), "members" => array(), "level" => $pos1["level"], "flags" => $this->config->get("DefaultFlags")));
                                                                    $this->areas->save();
                                                                    $s->sendMessage(F::GREEN . "[PRP] You create area " . $args[1] . " ( " . implode(", ", $min) . " - " . implode(", ", $max) . " )");
                                                                    $this->getLogger()->info(F::YELLOW . $s->getName() . " create his area " . $args[1] . " in world " . $player->getLevel()->getName());
                                                                    unset($this->pos1[strtolower($s->getName())]);
                                                                    unset($this->pos2[strtolower($s->getName())]);
                                                                    return;
                                                                } else {
                                                                    $s->sendMessage(F::RED . "[PRP] You area in other area!");
                                                                    return;
                                                                }
                                                            } else {
                                                                $s->sendMessage(F::RED . "[PRP] You already have " . $this->config->get("MaxAreas") . " delete one");
                                                                return;
                                                            }
                                                        }
                                                    } else {
                                                        $this->areas->set(strtolower($args[1]), array("min" => $min, "max" => $max, "owners" => array(strtolower($s->getName())), "members" => array(), "level" => $pos1["level"], "flags" => $this->config->get("DefaultFlags")));
                                                        $this->areas->save();
                                                        $s->sendMessage(F::GREEN . "[PRP] You create area " . $args[1] . " ( " . implode(", ", $min) . " - " . implode(", ", $max) . " )");
                                                        $this->getLogger()->info(F::YELLOW . $s->getName() . " create his area " . $args[1] . " in world " . $player->getLevel()->getName());
                                                        unset($this->pos1[strtolower($s->getName())]);
                                                        unset($this->pos2[strtolower($s->getName())]);
                                                        return;
                                                    }
                                                } else {
                                                    $s->sendMessage(F::RED . "[PRP] Max area size " . $this->config->get("MaxAreaSize") . " blocks,you area size " . ($maxX - $minX) * ($maxY - $minY) * ($maxZ - $minZ) . " blocks!");
                                                    return;
                                                }

                                            } else {
                                                $s->sendMessage(F::RED . "[PRP] You will set pos1 and pos2 in one world!");
                                                return;
                                            }
                                        } else {
                                            $s->sendMessage(F::RED . "[PRP] Area with this name already created!");
                                            return;
                                        }
                                    } else {
                                        $s->sendMessage(F::RED . "[PRP] Use /pa create <NAME>");
                                        return;
                                    }
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] You will set 1pos and 2pos position!");
                                    return;
                                }
                            } elseif (strtolower($args[0] == "info")) {
                                if (isset($args[1])) {
                                    if ($this->areas->exists(strtolower($args[1]))) {
                                        if (in_array(strtolower($s->getName()), $this->areas->get(strtolower($args[1]))["members"]) || in_array(strtolower($s->getName()), $this->areas->get(strtolower($args[1]))["owners"])) {
                                            $flagss = array();
                                            foreach ($this->areas->get(strtolower($args[1]))["flags"] as $flagg => $fss) {
                                                $flagss[] = $flagg . ":" . $fss;
                                            }
                                            $s->sendMessage(F::YELLOW . "Area name: " . strtolower($args[1]));
                                            $s->sendMessage(F::YELLOW . "Owners: " . implode(" , ", $this->areas->get(strtolower($args[1]))["owners"]));
                                            $s->sendMessage(F::YELLOW . "Members: " . implode(" , ", $this->areas->get(strtolower($args[1]))["members"]));
                                            $s->sendMessage(F::YELLOW . "Flags: ");
                                            $s->sendMessage(F::YELLOW . implode(", ", $flagss));
                                            return;
                                        } else {
                                            $s->sendMessage(F::RED . "[PRP] You don`t have permissions");
                                            return;
                                        }
                                    } else {
                                        $s->sendMessage(F::RED . "[PRP] Area " . $args[1] . " not exists");
                                    }
                                } else {
                                    foreach ($this->areas->getAll() as $name => $info) {
                                        $x = $s->getFloorX();
                                        $y = $s->getFloorY();
                                        $z = $s->getFloorZ();
                                        if ($this->checkCoordinates($info, $x, $y, $z)) {
                                            if (in_array(strtolower($s->getName()), $info["owners"]) || in_array(strtolower($s->getName()), $info["members"]) || $s->hasPermission("prp.doall")) {
                                                $this->forInfo[$s->getName()] = true;
                                                $this->forInfoCheckPerm[$s->getName()] = true;
                                            } else {
                                                $this->forInfo[$s->getName()] = true;
                                                $this->forInfoCheckPerm[$s->getName()] = false;
                                            }
                                        } else {
                                            continue;
                                        }
                                    }
                                    if (isset($this->forInfo[$s->getName()]) and $this->forInfo[$s->getName()] == true) {
                                        foreach ($this->areas->getAll() as $name => $info) {
                                            if ($this->checkCoordinates($info, $s->getFloorX(), $s->getFloorY(), $s->getFloorZ())) {
                                                if ($this->forInfoCheckPerm[$s->getName()] == true) {
                                                    $flags = array();
                                                    foreach ($info["flags"] as $flag => $fs) {
                                                        $flags[] = $flag . ":" . $fs;
                                                    }
                                                    $s->sendMessage(F::YELLOW . "Area: " . $name);
                                                    $s->sendMessage(F::YELLOW . "Owners: " . implode(", ", $info["owners"]));
                                                    $s->sendMessage(F::YELLOW . "Members: " . implode(", ", $info["members"]));
                                                    $s->sendMessage(F::YELLOW . "Flags: " . implode(", ", $flags));
                                                    unset($this->forInfoCheckPerm[$s->getName()]);
                                                    unset($this->forInfo[$s->getName()]);
                                                } else {
                                                    $s->sendMessage(F::RED . "[PRP] You don`t have permissions");
                                                    unset($this->forInfoCheckPerm[$s->getName()]);
                                                    unset($this->forInfo[$s->getName()]);
                                                }
                                            } else {
                                                continue;
                                            }
                                        }
                                    } else {
                                        unset($this->forInfoCheckPerm[$s->getName()]);
                                        unset($this->forInfo[$s->getName()]);
                                        $s->sendMessage(F::RED . "[PRP] Area not exists");
                                    }
                                }
                            } elseif (strtolower($args[0]) == "pinfo") {
                                $s->sendMessage(F::GREEN . "Private Region Protector V" . $this->getDescription()->getVersion());
                                $s->sendMessage(F::GREEN . "By Sergey Dertan from Ukraine");
                                return;
                            } elseif (strtolower($args[0]) == "remove") {
                                if (isset($args[1])) {
                                    if ($this->areas->exists(strtolower($args[1]))) {
                                        if (in_array(strtolower($s->getName()), $this->areas->get(strtolower($args[1]))["owners"]) || $s->hasPermission("prp.doall")) {
                                            $this->areas->remove(strtolower($args[1]));
                                            $this->areas->save();
                                            $s->sendMessage(F::YELLOW . "[PRP] Area " . $args[1] . " has removed!");
                                            return;
                                        } else {
                                            $s->sendMessage(F::RED . "[PRP] You don`t have permissions");
                                            return;
                                        }
                                    } else {
                                        $s->sendMessage(F::RED . "[PRP] Area " . $args[1] . " does not exists");
                                        return;
                                    }
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa remove <AREA>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "addmember") {
                                if (isset($args[1]) && isset($args[2])) {
                                    $s->sendMessage(F::YELLOW . $this->ROAPFA($s, $args[2], $args[1], "members", "add"));
                                    return;
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa addmember <AREA> <PLAYER>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "addowner") {
                                if (isset($args[1]) && isset($args[2])) {
                                    $s->sendMessage(F::YELLOW . $this->ROAPFA($s, $args[2], $args[1], "owners", "add"));
                                    return;
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa addowner <AREA> <PLAYER>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "removemember") {
                                if (isset($args[1]) && isset($args[2])) {
                                    $s->sendMessage(F::YELLOW . $this->ROAPFA($s, $args[2], $args[1], "members", "remove"));
                                    return;
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa removemember <AREA> <PLAYER>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "removeowner") {
                                if (isset($args[1]) && isset($args[2])) {
                                    $s->sendMessage(F::YELLOW . $this->ROAPFA($s, $args[2], $args[1], "owners", "remove"));
                                    return;
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa removeowner <AREA> <PLAYER>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "list") {
                                $forList = array();
                                foreach ($this->areas->getAll() as $name => $info) {
                                    if (in_array(strtolower($s->getName()), $info["owners"])) {
                                        $forList[] = $name;
                                    } else {
                                        continue;
                                    }
                                }
                                if (count($forList) != 0) {
                                    $s->sendMessage(F::YELLOW . "[PRP] You areas :");
                                    $s->sendMessage(F::YELLOW . implode(", ", $forList));
                                    return;
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] You don`t have areas");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "flag") {
                                if (isset($args[1]) && isset($args[2]) && isset($args[3])) {
                                    if ($this->areas->exists(strtolower($args[1]))) {
                                        if (in_array(strtolower($s->getName()), $this->areas->getAll()[$args[1]]["owners"]) || $s->hasPermission("prp.doall")) {
                                            if (strtolower($args[2]) == "pvp" || strtolower($args[2]) == "build" || strtolower($args[2]) == "use" || strtolower($args[2]) == "send-chat" || strtolower($args[2]) == "entry" || strtolower($args[2]) == "explode" || strtolower($args[2]) == "burn" || strtolower($args[2]) == "regain" || strtolower($args[2]) == "teleport" || strtolower($args[2]) == "god-mode" || strtolower($args[2]) == "sleep" || strtolower($args[2]) == "mob-damage" || strtolower($args[2]) == "tnt-explode" || strtolower($args[2]) == "drop-item") {
                                                if (strtolower($args[3]) == "allow" || strtolower($args[3]) == "deny") {
                                                    if ($s->hasPermission("flag." . strtolower($args[2])) || $s->hasPermission("prp.doall")) {
                                                        $s->sendMessage(F::YELLOW . $this->SetFlag(strtolower($args[2]), strtolower($args[3]), strtolower($args[1])));
                                                        return;
                                                    } else {
                                                        $s->sendMessage(F::RED . "[PRP] You don`t have permission to use flag " . $args[2]);
                                                        return;
                                                    }
                                                } else {
                                                    $s->sendMessage(F::RED . "[PRP] Flag can be only allow or deny");
                                                    return;
                                                }
                                            } else {
                                                $s->sendMessage(F::RED . "[PRP] Not correct flag!");
                                                $s->sendMessage(F::RED . "Fags: use,pvp,build,send-chat,entry,god-mode,");
                                                $s->sendMessage(F::RED . "teleport,mob-damage,sleep,explode,tnt-explode");
                                                return;
                                            }
                                        } else {
                                            $s->sendMessage(F::RED . "[PRP] You don`t have permissions");
                                            return;
                                        }
                                    } else {
                                        $s->sendMessage(F::RED . "[PRP] Area " . $args[1] . " not exists");
                                        return;
                                    }
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa flag <AREA> <FLAG> <TRUE/FALSE>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "help") {
                                $s->sendMessage(F::YELLOW . "pa create <NAME>");
                                $s->sendMessage(F::YELLOW . "pa pos1/pos2");
                                $s->sendMessage(F::YELLOW . "pa addmember/removemember");
                                $s->sendMessage(F::YELLOW . "pa addowner/removeowner");
                                $s->sendMessage(F::YELLOW . "pa flag <AREA> <FLAG> <ALLOW/DENY>");
                                $s->sendMessage(F::YELLOW . "pa info/pa info <AREA>");
                                $s->sendMessage(F::YELLOW . "pa wand");
                                $s->sendMessage(F::YELLOW . "pa clear");
                            } elseif (strtolower($args[0]) == "wand") {
                                $player = $s->getServer()->getPlayer($s->getName());
                                $wand = Item::get(271, 0, 1);
                                if ($player->getInventory()->canAddItem($wand)) {
                                    $player->getInventory()->addItem($wand);
                                    $s->sendMessage(F::RED . "[PRP] You get protect wand!");
                                    return;
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] You inventory full!");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "expand") {
                                if (isset($args[1]) and isset($args[2])) {
                                    if (strtolower($args[1]) == "up" or strtolower($args[1]) == "down") {
                                        if (is_numeric($args[2])) {
                                            if (isset($this->pos1[strtolower($s->getName())]) and isset($this->pos2[strtolower($s->getName())])) {
                                                if ($args[1] == "up") {
                                                    $this->pos1[strtolower($s->getName())]["1"] = $this->pos1[strtolower($s->getName())]["1"] + $args[2];
                                                    $s->sendMessage(F::RED . "[PRP] Selected expanded to " . $args[2] . " blocks up");
                                                    return;
                                                } else {
                                                    $this->pos2[strtolower($s->getName())]["1"] = $this->pos2[strtolower($s->getName())]["1"] - $args[2];
                                                    $s->sendMessage(F::YELLOW . "[PRP] Area expanded to " . $args[2] . " blocks down");
                                                    return;
                                                }
                                            } else {
                                                $s->sendMessage(F::RED . "[PRP] Set POS1 and POS2 first");
                                                return;
                                            }
                                        } else {
                                            $s->sendMessage(F::RED . "[PRP] <BLOCKS> must be numeric!");
                                            return;
                                        }
                                    } else {
                                        $s->sendMessage(F::RED . "[PRP] Use only /pa expand <UP/DOWN>");
                                        return;
                                    }
                                } else {
                                    $s->sendMessage(F::RED . "[PRP] Use /pa expand <UP/DOWN> <BLOCKS>");
                                    return;
                                }
                            } elseif (strtolower($args[0]) == "clear") {
                                if (isset($this->pos1[strtolower($s->getName())])) unset($this->pos1[$s->getName()]);
                                if (isset($this->pos2[strtolower($s->getName())])) unset($this->pos2[$s->getName()]);
                                $s->sendMessage(F::YELLOW . "[PRP] Selected cleared!");
                            } elseif (strtolower($args[0]) != "create" || "pos1" || "pos2" || "remove" || "pinfo" || "info" || "addmember" || "list" || "addowner" || "removemember" || "wand" || "flag") {
                                $s->sendMessage(F::RED . "Sub command " . $args[0] . " does not exists!");
                                $s->sendMessage(F::RED . "Use /pa,to see all commands!");
                                return;
                            }
                        } else {
                            $s->sendMessage(F::RED . "[PRP] Use /pa <CREATE|POS1|POS2|REMOVE|ADDMEMBER|ADDOWNER|REMOVEOWNER|REMOVEMEMBER|PINFO|INFO|LIST>");
                            return;
                        }
                    } else {
                        $s->sendMessage(F::RED . "[PRP] You don`t have permission!");
                        return;
                    }
                } else {
                    $s->sendMessage(F::RED . "[PRP] This command work in game only!(/pa)");
                    return;
                }
        }
    }


    /***
     * @param $info
     * @param $x
     * @param $y
     * @param $z
     * @return bool
     */
    function checkCoordinates($info, $x, $y, $z)
    {
        if ($info["min"][0] <= $x and $x <= $info["max"][0]) {
            if ($info["min"][1] <= $y and $y <= $info["max"][1]) {
                if ($info["min"][2] <= $z and $z <= $info["max"][2]) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $flag
     * @param $flagStatus
     * @param $area
     * @return string
     */
    private function SetFlag($flag, $flagStatus, $area)
    {
        $AA = $this->areas->getAll();
        $AA[$area]["flags"][$flag] = $flagStatus;
        $this->areas->setAll($AA);
        $this->areas->save();
        return "[PRP] Flag {$flag} in area {$area} set to {$flagStatus}";
    }

    /***
     * @param Player $p
     * @param $flag
     * @param $flagB
     * @param $cP
     * @return bool
     */
    function checkF(Player $p, $flag, $flagB, $cP)
    {
        $pos = $p;
        foreach ($this->areas->getAll() as $name => $info) {
            if ($this->checkCoordinates($info, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()) and $pos->getLevel()->getName() == $info["level"]) {
                if ($info["flags"][$flag] == $flagB) {
                    if ($cP == true) {
                        if (in_array(strtolower($p->getName()), $info["owners"]) or in_array(strtolower($p->getName()), $info["members"]) || $p->hasPermission("prp.doall")) {
                            $this->forCF[strtolower($p->getName())] = false;
                        } else {
                            $this->forCF[strtolower($p->getName())] = true;
                        }
                    } else {
                        $this->forCF[strtolower($p->getName())] = true;
                    }
                } else {
                    continue;
                }
            } else {
                continue;
            }
        }
        if (isset($this->forCF[strtolower($p->getName())])) {
            if ($this->forCF[strtolower($p->getName())] == true) {
                unset($this->forCF[strtolower($p->getName())]);
                return true;
            } elseif
            ($this->forCF[strtolower($p->getName())] == false
            ) {
                unset($this->forCF[strtolower($p->getName())]);
                return false;
            } elseif ($this->forCF[strtolower($p->getName())] != false and $this->forCF[strtolower($p->getName())] != false) {
                unset($this->forCF[strtolower($p->getName())]);
                return false;
            }
        } else {
            return false;
        }
        return false;
    }

    /***
     * @param Player $sender
     * @param $playerForAddOrRemove
     * @param $rg
     * @param $fromRemove
     * @param $removeOrAdd
     * @return string
     */
    private function ROAPFA(Player $sender, $playerForAddOrRemove, $rg, $fromRemove, $removeOrAdd)
    {
        $PFAOR = strtolower($playerForAddOrRemove);
        $area = strtolower($rg);
        $areas = $this->areas->getAll();
        $ROA = strtolower($removeOrAdd);
        $FR = strtolower($fromRemove);
        if (isset($areas[$area])) {
            if (in_array(strtolower($sender->getName()), $areas[$area]["owners"]) or $sender->hasPermission("prp.doall")) {
                if ($ROA == "add") {
                    $list = $areas[$area][$FR];
                    $list[] = $PFAOR;
                    $areas[$areas][$FR] = $list;
                    $this->areas->setAll($areas);
                    $this->areas->save();
                    return "[PRP] {$PFAOR} add to {$FR} in area {$area}";
                } else {
                    $rlist = $areas[$area][$FR];
                    $key = array_search($PFAOR, $rlist);
                    unset($rlist[$key]);
                    $areas[$area][$FR] = $rlist;
                    $this->areas->setAll($areas);
                    $this->areas->save();
                    return "[PRP] Player {$PFAOR} has removed from {$FR} in area {$area}";
                }
            } else {
                return "[PRP] You don't have permissions!";
            }
        } else {
            return "[PRP] Area {$area} not exists";
        }
    }
    function FEntity(Entity $entity, $flag, $fnb)
    {
        foreach ($this->areas->getAll() as $area)
        {
            if($area["flags"][$flag] == $fnb){
                if($this->checkCoordinates($area, $entity->x, $entity->y, $entity->z)){
                    return true;
                }
            }
        }
        return false;
    }

    function onDisable()
    {
        $this->getLogger()->info(F::RED . "Private Region Protector V_" . $this->getDescription()->getVersion() . " by Sergey Dertan unload!");
    }
}
