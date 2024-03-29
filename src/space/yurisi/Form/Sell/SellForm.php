<?php
declare(strict_types=1);

namespace space\yurisi\Form\Sell;

use pocketmine\form\Form;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use space\yurisi\Config\YamlConfig;
use space\yurisi\Form\MainForm;

class SellForm implements Form {

  public function handleResponse(Player $player, $data): void {
    if (!is_numeric($data)) return;
    switch ($data) {
      case 0:
        $player->sendForm(new SellRegisterForm($player));
        return;
      case 1:
        $cls = new YamlConfig();
        if ($cls->getMarketPlayer($player->getName()) === null) {
          $player->sendMessage("[§aTRADE§r] 出品していません");
          return;
        }
        $player->sendForm(new ConfirmMyMarketForm($cls->getMarketPlayer($player->getName())));
        return;
      case 2:
        if ($player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
          $cls = new YamlConfig();
          if ($cls->getPrivateAllMarket() == null) {
            $player->sendMessage("[§aTRADE§r] フリーマーケットがありません");
            return;
          }
          foreach ($cls->getPrivateAllMarket() as $id) {
            $buttons[] = $cls->getMarketData($id);
          }
          $player->sendForm(new ConfirmMyMarketForm($buttons));
          return;
        }
        $player->sendMessage("OP以外は使えません。");
        return;
    }
    $player->sendForm(new MainForm());
  }


  public function jsonSerialize() {
    $buttons[] = ['text' => "出品する"];
    $buttons[] = ['text' => "自分の出品リスト"];
    $buttons[] = ['text' => "OPメニュー"];
    $buttons[] = ['text' => "戻る"];
    return [
      "type" => 'form',
      "title" => '§l§atrade',
      "content" => "フリーマーケットメニューです！",
      "buttons" => $buttons,
    ];
  }
}

class SellRegisterForm implements Form {

  private Player $player;

  private string $text;

  private array $list = [];

  public function __construct(Player $player, string $text = "") {
    $this->player = $player;
    $this->text = $text;
  }

  public function handleResponse(Player $player, $data): void {
    if (!$data) return;
    if (!$this->list[$data[1]] instanceof Item) {
      $player->sendForm(new self($player, "§c不明なエラーです。やり直してください。"));
      return;
    }
    if (!is_numeric($data[2])) {
      $player->sendForm(new self($player, "§c個数は整数で入力してください"));
      return;
    }
    if ($data[2] <= 0) {
      $player->sendForm(new self($player, "§c個数は+で入力してください"));
      return;
    }
    if ($this->list[$data[1]]->getId() === 218) {
      if ($data[2] > 1) {
        $player->sendForm(new self($player, "§cシュルカーボックスは1個までしか出品できません"));
        return;
      }
    }
    if (!is_numeric($data[3])) {
      $player->sendForm(new self($player, "§c値段は整数で入力してください"));
      return;
    }
    if ($data[3] <= 0) {
      $player->sendForm(new self($player, "§c値段は+で入力してください"));
      return;
    }

    $amount = floor((int)$data[2]);
    $price = floor((int)$data[3]);
    $count = 0;

    foreach ($player->getInventory()->getContents() as $item) {
      if ($this->list[$data[1]]->getId() == $item->getId()) {
        if ($this->list[$data[1]]->getMeta() == $item->getMeta()) {
          $count += $item->getCount();
        }
      }
    }

    if ($count < $amount) {
      $player->sendForm(new self($player, "§cアイテムが足りません。"));
      return;
    }

    if ($this->list[$data[1]]->getId() === 378) {
      for ($i = 0; $i < $amount; $i++) {
        $item = ItemFactory::getInstance()->get(378, 0, 1);
        $player->getInventory()->removeItem($item);
      }

    } else {
      $this->list[$data[1]]->setCount((int)$amount);
      $item = Item::nbtDeserialize(($this->list[$data[1]]->nbtSerialize()));
      $player->getInventory()->removeItem($item);
    }

    $cls = new YamlConfig();
    $cls->registerItem((int)$price, $player, $data[4], $this->list[$data[1]]->nbtSerialize());
    $player->sendMessage("[§aTRADE§r] §a出品が完了しました！ ID:" . $cls->getLastId());
  }

  public function jsonSerialize() {
    $content[] = [
      "type" => "label",
      "text" => $this->text
    ];
    $content[] = [
      "type" => "dropdown",
      "text" => "売るアイテムを選択してください"
    ];
    $content[] = [
      "type" => "input",
      "text" => "個数を入力してください"
    ];
    $content[] = [
      "type" => "input",
      "text" => "売る値段を入力してください"
    ];
    $content[] = [
      "type" => "toggle",
      "text" => "プライベートにする"
    ];
    foreach ($this->player->getInventory()->getContents() as $item) {
      $content[1]["options"][] = $item->getName() . "§r";
      $this->list[] = $item;
    }
    return [
      'type' => 'custom_form',
      'title' => '§l§atrade/Sell',
      'content' => $content
    ];
  }


}

class ConfirmMyMarketForm implements Form {

  private array $data;

  public function __construct(array $data) {
    $this->data = $data;
  }

  public function handleResponse(Player $player, $data): void {
    if (!is_numeric($data)) return;
    $player->sendForm(new SelectMyMarketForm($this->data[$data]));
  }


  public function jsonSerialize() {
    $button = array();
    foreach ($this->data as $data) {
      $item = YamlConfig::getItem($data["id"]);
      if ($item instanceof Item) {
        $name = $item->getName() . "§r";
        $ans = $data["public"] ? "非公開" : "公開";
        $button[] = ['text' => "{$name} : {$item->getCount()}個\n{$data["price"]}￥ ItemID_Damage:{$item->getId()}_{$item->getMeta()} ID:{$data["id"]} 公開 : {$ans}"];
      } else {
        return [];
      }
    }
    return [
      "type" => 'form',
      "title" => "§l§atrade/Mydata",
      "content" => "",
      "buttons" => $button,
    ];
  }
}

class SelectMyMarketForm implements Form {

  private array $data;

  public function __construct(array $data) {
    $this->data = $data;
  }

  public function handleResponse(Player $player, $data): void {
    if (!is_numeric($data)) return;
    $cls = new YamlConfig();
    switch ($data) {
      case 0:
        $item = YamlConfig::getItem($this->data["id"]);
        if ($item instanceof Item) {
          if ($player->getInventory()->canAddItem($item)) {
            $cls->removeItem($this->data["id"]);
            $player->sendMessage("[§aTRADE§r] 出品を取り消しました ID:{$this->data["id"]}");
            $player->getInventory()->addItem($item);
            return;
          }
        }
        $player->sendMessage("[§aTRADE§r] 持ち物が一杯で取り消しできませんでした");
        return;
      case 1:
        $player->sendForm(new EditMyMarketForm($this->data));
        return;
    }
    if ($cls->getMarketPlayer($player->getName()) == null) {
      $player->sendMessage("[§aTRADE§r] 出品していません");
      return;
    }
    $player->sendForm(new ConfirmMyMarketForm($cls->getMarketPlayer($player->getName())));
  }

  public function jsonSerialize() {
    $item = YamlConfig::getItem($this->data["id"]);
    if ($item instanceof Item) {
      $name = $item->getName() . "§r";
      $ans = $this->data["public"] ? "非公開" : "公開";
      $content = "{$name} : {$item->getCount()}個\n{$this->data["price"]}￥ {$this->data["player"]}さん出品 ItemID_Damage:{$item->getId()}_{$item->getMeta()} ID : {$this->data["id"]} 公開 : {$ans}";
      return [
        "type" => 'form',
        "title" => "§l§atrade/Mydata",
        "content" => $content,
        "buttons" => [
          ['text' => "出品を取り消す"],
          ['text' => "編集する"],
          ['text' => "一個前に戻る"]
        ],
      ];
    }
    return [];
  }
}

class EditMyMarketForm implements Form {

  private array $data;

  private string $label;

  public function __construct(array $data, string $label = "") {
    $this->data = $data;
    $this->label = $label;
  }

  public function handleResponse(Player $player, $data): void {
    if ($data == false) return;
    if (!is_numeric($data[1])) {
      $player->sendForm(new self($this->data, "\n§c値段は整数で入力してください。"));
      return;
    }
    $amount = floor((int)$data[1]);
    $cls = new YamlConfig();
    $cls->editMarketItem($this->data["id"], (int)$amount, $data[2]);
    $ans = $data[2] ? "非公開" : "公開";
    $player->sendMessage("[§aTRADE§r] {$amount}円で{$ans}に設定しました");
  }

  public function jsonSerialize() {
    $item = YamlConfig::getItem($this->data["id"]);
    if ($item instanceof Item) {
      $content[] = [];
      $name = $item->getName() . "§r";
      $ans = $this->data["public"] ? "非公開" : "公開";
      $content[] = [
        "type" => "label",
        "text" => "{$name} : {$item->getCount()}個\n{$this->data["price"]}￥ {$this->data["player"]}さん出品 ItemID_Damage:{$item->getId()}_{$item->getMeta()} ID : {$this->data["id"]}\n公開 : {$ans}" . $this->label
      ];
      $content[] = [
        "type" => "input",
        "text" => "売る値段を入力してください"
      ];
      $content[] = [
        "type" => "toggle",
        "text" => "プライベートにする"
      ];
    }
    return [
      'type' => 'custom_form',
      'title' => '§l§atrade/Sell',
      'content' => $content
    ];
  }
}