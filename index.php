<?php declare(strict_types=1);
/**
 * Var2. Messenger.
Imagine, you are developing a messenger with direct messages, groups, different media types of messages, etc. You need to keep your messenger state in memory: all users with their data, which chats they have, etc.  It is expected that your class has following methods:
Display methods and properties to check available chats and contacts.
Create chats, add user to chat, remove user from chat.
Write and receive messages.
Find messages with some word combination.
 */
// Domain Model - Represent single instance of a domain entity

interface Content {};

class TextContent implements Content {
    public string $text;
    public function __construct(string $text)
    {
        $this->text = $text;
    }
}
class GroupMessage {
    public User $sender;
    public GroupChat $chat;
    public Content $content;

    public function __construct(User $sender, GroupChat $chat, Content $content)
    {
        $this->sender = $sender;
        $this->chat = $chat;
        $this->content = $content;
    }
}
class ContentPrinter {
    public function print(Content $content):string
    {
        if ($content instanceof TextContent) {
            return $content->text;
        }
        return sprintf('No implementation for %s to print it', get_class($content));
    }
}
class PrivateMessage {
    public User $sender;
    public User $receiver;
    public Content $content;

    public function __construct(User $sender, User $receiver, Content $content)
    {
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->content = $content;
    }

}


class User {
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

class GroupChat {
    /** @var array<string, User> */
    private array $members;
    public string $name;

    public function __construct(string $name, array $members)
    {
        $this->members = $members;
        $this->name = $name;
    }
    public function addMember(User $user):void
    {
        $this->members[spl_object_id($user)] = $user;
    }
    public function removeMember(User $user):void
    {
        unset($this->members[spl_object_id($user)]);
    }

    public function getMembers():array {
        return [... $this->members];
    }
}

// Repository - Resposible for storing and retriging entiry form and to storage (In Memory database)

class PrivateMessageRepository {
    /**
     * @var array<string, PrivateMessage>
     */
    private array $messages;

    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function persistMessage(PrivateMessage $message):void
    {
        $this->messages[spl_object_id($message)] = $message;
    }

    /**
     * @return array<string, PrivateMessage>
     */
    public function findBetweenUsers(User $first, User $second):array
    {
        $participants =  [$first, $second];
        return array_filter(
            $this->messages,
            static fn(PrivateMessage $message):bool =>
                in_array($message->sender, $participants, true) && in_array($message->receiver, $participants, true),
        );
    }
    /**
     * @return array<int, PrivateMessage>
     */
    public function findAllForUser(User $user):array
    {
        return array_filter(
            $this->messages,
            static fn(PrivateMessage $message):bool =>
                $message->sender === $user || $message->receiver === $user,
        );
    }
}

class GroupMessageRepository {
    /**
     * @var array<string, GroupMessage>
     */
    private array $messages;

    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function persistMessage(GroupMessage $message):void
    {
        $this->messages[spl_object_id($message)] = $message;
    }

    /**
     * @return array<array-key, GroupMessage>
     */
    public function getAllForGroupChat(GroupChat $groupChat):array
    {
        return array_filter($this->messages, static fn(GroupMessage $message) => $message->chat === $groupChat);
    }
}


//
class Messenger {
    private PrivateMessageRepository $privateMessages;
    private GroupMessageRepository $groupMessages;

    public function __construct(PrivateMessageRepository $messageRepository, GroupMessageRepository $groupMessageRepository)
    {
        $this->privateMessages = $messageRepository;
        $this->groupMessages = $groupMessageRepository;
    }

    public function sendMessageToPrivetChat(PrivateMessage $message):void
    {
        $this->privateMessages->persistMessage($message);
    }

    /**
     * @param User $max
     * @return array<int, PrivateMessage>
     */
    public function getAllPrivateMessagesForUser(User $max):array
    {
        return $this->privateMessages->findAllForUser($max);
    }

    public function sendGroupMessage(GroupMessage $groupMessage):void
    {
        $this->groupMessages->persistMessage($groupMessage);
    }

    /**
     * @return array<GroupMessage>
     */
    public function getAllMessagesInGroup(GroupChat $groupChat): array
    {
        return $this->groupMessages->getAllForGroupChat($groupChat);
    }

}

// Example
$messageRepository = new PrivateMessageRepository([]);
$groupMessageRepository = new GroupMessageRepository([]);
$messenger = new Messenger(new PrivateMessageRepository([]), $groupMessageRepository);
$printer = new ContentPrinter();

$max = new User('Max');
$ratsoa = new User('Ratsoa');

$messenger->sendMessageToPrivetChat(new PrivateMessage( $max, $ratsoa, new TextContent('Hi, Ratsoa')));
$messenger->sendMessageToPrivetChat( new PrivateMessage($ratsoa, $max, new TextContent('Hi, Max :)')));
$messenger->sendMessageToPrivetChat( new PrivateMessage($ratsoa, $max, new TextContent('How are you?')));



foreach ($messenger->getAllPrivateMessagesForUser($max) as $message){
    printf(
        "'%s' -> '%s':\t %s\n",
        $message->sender->name,
        $message->receiver->name,
        $printer->print($message->content)
    );

}

$alex = new User('Alex');
$viktor = new User('Victor');
$karl = new User('Karl');

$groupChat = new GroupChat('Old friends', [$alex, $viktor, $karl]);

$kate = new User('Kate');
$groupChat->addMember($kate);

$messenger->sendGroupMessage( new GroupMessage($alex, $groupChat, new TextContent('Hello everyone')));
$messenger->sendGroupMessage( new GroupMessage($kate, $groupChat, new TextContent('Hi, Karl! 😊')));
$messenger->sendGroupMessage( new GroupMessage($viktor, $groupChat, new TextContent('How are you, Kate?')));


printf("Chat '%s':\n", $groupChat->name);
printf(
    "Members:\t '%s'\n",
    implode(
        ', ',
        array_map(static fn(User $user):string => $user->name, $groupChat->getMembers())
    )
);
foreach ($messenger->getAllMessagesInGroup($groupChat) as $message) {
    printf("%s:\t%s\n", $message->sender->name, $printer->print($message->content));
}