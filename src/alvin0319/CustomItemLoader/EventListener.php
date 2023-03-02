<?php

/*
 *    ____          _                  ___ _                 _                    _
 *   / ___|   _ ___| |_ ___  _ __ ___ |_ _| |_ ___ _ __ ___ | |    ___   __ _  __| | ___ _ __
 *  | |  | | | / __| __/ _ \| '_ ` _ \ | || __/ _ \ '_ ` _ \| |   / _ \ / _` |/ _` |/ _ \ '__|
 *  | |__| |_| \__ \ || (_) | | | | | || || ||  __/ | | | | | |__| (_) | (_| | (_| |  __/ |
 *   \____\__,_|___/\__\___/|_| |_| |_|___|\__\___|_| |_| |_|_____\___/ \__,_|\__,_|\___|_|
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace alvin0319\CustomItemLoader;

use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\ItemComponentPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionStopBreak;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\Position;
use function floor;
use function implode;

final class EventListener implements Listener
{

    private ?ItemComponentPacket $cachedItemComponentPacket = null;

    /** @var TaskHandler[][] */
    protected array $handlers = [];

    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $packets = $event->getPackets();
        foreach ($packets as $packet) {
            if ($packet instanceof StartGamePacket) {
                $packet->levelSettings->experiments = new Experiments([
                    "data_driven_items" => true
                ], true);
            } elseif ($packet instanceof ResourcePackStackPacket) {
                $packet->experiments = new Experiments([
                    "data_driven_items" => true
                ], true);
//            } elseif ($packet instanceof BiomeDefinitionListPacket) {
//                foreach ($event->getTargets() as $session) {
//                    $session->sendDataPacket(CustomItemManager::getInstance()->getPacket());
//                }
            } elseif ($packet instanceof ItemComponentPacket) {
                $entries = $packet->getEntries();
                if ($this->cachedItemComponentPacket === null) {
                    $this->cachedItemComponentPacket = ItemComponentPacket::create(array_merge($entries, CustomItemManager::getInstance()->getPacketEntry()));
                }
                if (count($entries) === count($this->cachedItemComponentPacket->getEntries())) return;

                foreach ($event->getTargets() as $session) {
                    $session->sendDataPacket($this->cachedItemComponentPacket);
                }
            }
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        if (!isset($this->handlers[$player->getName()])) {
            return;
        }
        foreach ($this->handlers[$player->getName()] as $blockHash => $handler) {
            $handler->cancel();
        }
        unset($this->handlers[$player->getName()]);
    }
}
