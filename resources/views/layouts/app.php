<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'BXCode') ?></title>
</head>
<body>
    <main>
        <?= $this->yieldSection('content') ?>
    </main>
    <footer>
        <?php $this->insert('partials.footer') ?>
    </footer>
</body>
</html>
