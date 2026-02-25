<!doctype html>
<html>

<head>
    <title><?php echo e($config->get('ui.title', config('app.name') . ' - API Docs')); ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
</head>

<body>
    <!-- Add your own OpenAPI/Swagger specification URL here: -->
    <script id="api-reference" type="application/json">
        <?php echo json_encode($spec, 15, 512) ?>
    </script>

    <!-- Optional: You can set a full configuration object like this: -->
    <script>
        var configuration = {
        theme: 'saturn',
        hideDownloadButton: true,
    }

    document.getElementById('api-reference').dataset.configuration =
        JSON.stringify(configuration)
    </script>
    <script src="<?php echo e(asset('standalone.js')); ?>"></script>
</body>

</html>
<?php /**PATH /Users/emmanuelikeogu/Laravelprojects/voltwatch-backend/resources/views/vendor/scramble/docs.blade.php ENDPATH**/ ?>