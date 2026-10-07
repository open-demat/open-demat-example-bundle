<?php

namespace OpenDemat\ExampleBundle\Tests\EventSubscriber;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Mailer\Message\TemplatedMailMessage;
use OpenDemat\Core\Mailer\Service\MailerService;
use OpenDemat\Core\Notification\BundleNotificationPreference;
use OpenDemat\Core\Notification\InboxService;
use OpenDemat\Core\Repository\TaskRepository;
use OpenDemat\ExampleBundle\Entity\DemandeAchatInterne;
use OpenDemat\ExampleBundle\EventSubscriber\DemandeAchatWorkflowSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Marking;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class WorkflowNotificationsTest extends TestCase
{
    public static function preferences(): iterable
    {
        yield 'default email enabled' => [false];
        yield 'muted example bundle' => [true];
    }

    #[DataProvider('preferences')]
    public function testValidationAlwaysArchivesAndOnlyEmailsUnmutedUsers(bool $muted): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE "user" (id INTEGER PRIMARY KEY, email TEXT)');
        $connection->executeStatement('CREATE TABLE user_bundle_profile (user_id INTEGER, bundle_key TEXT, notifications_muted BOOLEAN)');
        $connection->executeStatement('CREATE TABLE inbox_message (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, subject TEXT, body TEXT, bundle_key TEXT, created_at TEXT, read_at TEXT)');
        $connection->insert('user', ['id' => 1, 'email' => 'alice@example.org']);
        if ($muted) {
            $connection->insert('user_bundle_profile', ['user_id' => 1, 'bundle_key' => 'EXAMPLE', 'notifications_muted' => 1]);
        }

        $root = dirname(__DIR__, 5);
        $loader = new FilesystemLoader($root.'/templates');
        $loader->addPath($root.'/app_open_demat', 'OpenDemat');
        $twig = new Environment($loader);
        $twig->addGlobal('admin_url', 'https://example.org');
        $inbox = new InboxService($connection, new BodyRenderer($twig));
        $sent = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (TemplatedMailMessage $message) use (&$sent): Envelope {
            $sent[] = $message;
            return new Envelope($message);
        });
        $em = $this->createStub(EntityManagerInterface::class);
        $mailer = new MailerService($bus, $em, $connection, new BundleNotificationPreference($connection), $inbox, new NullLogger());
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://example.org/example/demandes-achat/42');
        $subscriber = new DemandeAchatWorkflowSubscriber($em, $this->createStub(TaskRepository::class), $this->createStub(Security::class), $mailer, $urls);
        $user = (new User())->setUsername('alice')->setEmail('alice@example.org');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 1);
        $demande = (new DemandeAchatInterne())->setAuteur($user)->setDemandeur('Alice')->setIntituleBesoin('<script>unsafe</script>')->setStatutTraitement('validee');
        (new \ReflectionProperty(DemandeAchatInterne::class, 'id'))->setValue($demande, 42);

        $subscriber->onValider(new Event($demande, new Marking()));

        self::assertCount($muted ? 0 : 1, $sent);
        self::assertSame(1, $inbox->unreadCount(1));
        $message = $inbox->page(1, 1)[0];
        self::assertSame('EXAMPLE', $message['bundle_key']);
        self::assertStringContainsString('&lt;script&gt;', $inbox->findForUser((int) $message['id'], 1)['body']);
    }
}
