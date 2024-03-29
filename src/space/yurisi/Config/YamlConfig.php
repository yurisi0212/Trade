<?php
declare(strict_types=1);

namespace space\yurisi\Config;

use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use space\yurisi\Trade;

class YamlConfig extends Config {

  private static Config $config;

  public function __construct() {
    parent::__construct(Trade::getInstance()->getDataFolder() . "trade_v2.yml", Config::YAML);
    self::$config=$this;
  }

  public function registerItem(int $price, Player $player, bool $bool,CompoundTag $nbt) {
    $id = $this->getRegisterId();
    $this->set($id, array(
      "id" => $id,
      "price" => $price,
      "player" => $player->getName(),
      "public" => $bool,
      "nbt"=>$nbt
    ));
    $this->save();
  }

  public function removeItem(int $id) {
    if($this->exists($id)) {
      $this->remove($id);
      $this->save();
    }
  }

  private function getRegisterId(): int {
    $id = 0;
    if (count($this->getAll()) > 0) {
      $data = $this->getAll();
      $id = ++end($data)["id"];
    }
    return $id;
  }

  public function getLastId(): int {
    $id = 0;
    if (count($this->getAll()) > 0) {
      $data = $this->getAll();
      $id = end($data)["id"];
    }
    return $id;
  }

  public function editMarketItem(int $id,int $price,bool $option){
    if($this->exists($id)) {
      $this->set($id, array(
        "id" => $id,
        "price" => $price,
        "player" => $this->get($id)["player"],
        "public" => $option,
        "nbt" => $this->get($id)["nbt"],
      ));
      $this->save();
    }

  }

  public function getMarketItem(int $id, int $damage = 0): array {
    $adata = $this->getAll();
    $items = [];
    foreach ($adata as $data) {
      if (isset($data["id"])) {
        $item = Item::nbtDeserialize(unserialize($data["nbt"]));
        if ($item->getId() == $id) {
          if (!$data["public"]) {
            if (self::isTools($id)) {
              $items[] = $data["id"];
            } else {
              if ($item->getMeta() == $damage) {
                $items[] = $data["id"];
              }
            }
          }
        }
      }
    }
    return $items;
  }

  public function getAllMarket():array{
    $adata = $this->getAll();
    $items = [];
    foreach ($adata as $data) {
      if (!$data["public"]) {
        $items[] = $data["id"];
      }
    }
    return $items;
  }

  public function getPrivateAllMarket():array{
    $adata = $this->getAll();
    $items = [];
    foreach ($adata as $data) {
      $items[] = $data["id"];
    }
    return $items;
  }

  public function getMarketPlayer(string $name):array {
    $adata = $this->getAll();
    $items = [];
    foreach ($adata as $data) {
      if ($data["player"]==$name) {
        $items[] = $this->getMarketData($data["id"]);
      }
    }
    return $items;
  }

  public function getMarketData(int $id): array {
    if (!$this->exists($id)) return [];
    return $this->get($id);
  }


  public static function isTools(int $id) {
    $item = ItemFactory::getInstance()->get($id, 0, 1);
    if ($item instanceof Durable) {
      return true;
    }
    return false;
  }

  public static function getItem(int $id): ?Item{
    if(self::$config->exists($id)) {
      return Item::nbtDeserialize(unserialize(self::$config->get($id)["nbt"]));
    }
    return null;
  }
}