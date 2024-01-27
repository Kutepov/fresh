<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\AdBanner $model
 */

$this->title = 'Добавить рекламный баннер';
$this->params['breadcrumbs'][] = ['label' => 'Рекламные баннеры', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ad-banner-create">
    <div class="page-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
