<?php
/** @var \App\View\AppView $this */
/** @var string $merchantId */
$this->assign('title', 'Dashboard');
$this->setLayout('dashboard');
?>
<div class="container-fluid p-0">

    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">

            <?= $this->element('topbar') ?>

            <?= $this->element('stats') ?>

            <?= $this->element('charts') ?>

            <?= $this->element('recent_customers') ?>

        </main>

    </div>

</div>
