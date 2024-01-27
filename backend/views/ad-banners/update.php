<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\AdBanner $model
 */

$this->title = 'Редактировать рекламный баннер: ' . ' ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Рекламные баннеры', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="ad-banner-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
