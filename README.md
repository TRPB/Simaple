# Simaple

A simple PHP imap account reader.

*This is a work in progress and I've only added features as I've needed them so it's not a complete IMAP library but for simple
tasks like reading emails and getting attachments the API is far friendlier than the php imap_ functions*

usage:

```php
$account = new \ImapReader\EmailAccount('user@domain.com', 'password', 'imap.example.org', array('nonvalidate-cert'));

foreach ($email->getMessages() as $message) {

foreach ($message->getAttachments() as $attachment) {

}

}

```
