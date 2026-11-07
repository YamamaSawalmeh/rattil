<?php

function rattil_page_start($title='رتّل'){
?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $title ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

    <style>

        :root{

            --olive-950:#102418;
            --olive-900:#163324;
            --olive-700:#1d4a32;
            --olive-500:#2d7a52;
            --olive-300:#76a98f;

            --surface:#f7f3ec;
            --surface-2:#ffffff;

            --border:#e7e5e4;

            --muted:#6b7280;

            --danger:#dc2626;

            --success:#16a34a;

            --r-md:16px;

            --shadow:
                    0 10px 30px rgba(0,0,0,.06);
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{

            background:var(--surface);

            color:var(--olive-900);

            font-family:'Tajawal',sans-serif;
        }

        a{
            text-decoration:none;
            color:inherit;
        }

        .sidebar{

            position:fixed;

            left:0;
            top:0;

            width:280px;

            height:100vh;

            background:var(--olive-950);

            padding:2rem 1rem;

            overflow:auto;
        }

        .brand{

            color:#fff;

            font-size:1.5rem;

            font-weight:800;

            margin-bottom:2rem;
        }

        .sidebar a{

            display:flex;

            align-items:center;

            gap:.75rem;

            padding:1rem;

            border-radius:12px;

            color:#d1d5db;

            margin-bottom:.5rem;

            transition:.25s;
        }

        .sidebar a:hover,
        .sidebar a.active{

            background:rgba(255,255,255,.08);

            color:#fff;
        }

        .main{

            margin-left:280px;

            padding:2rem;
        }

        .card{

            background:#fff;

            border-radius:20px;

            padding:1.5rem;

            box-shadow:var(--shadow);
        }

        .card-head{

            display:flex;

            justify-content:space-between;

            align-items:flex-start;

            gap:1rem;
        }

        .card-title{

            display:flex;

            align-items:center;

            gap:.6rem;

            font-size:1.2rem;

            font-weight:800;
        }

        .card-title svg{

            width:20px;
            height:20px;

            stroke:var(--olive-500);

            fill:none;

            stroke-width:2;
        }

        .badge{

            padding:.4rem .8rem;

            border-radius:999px;

            font-size:.8rem;

            font-weight:700;
        }

        .badge-active{

            background:#eaf8ef;

            color:#15803d;
        }

        .badge-danger{

            background:#fee2e2;

            color:#b91c1c;
        }

        .btn{

            border:none;

            cursor:pointer;

            border-radius:12px;

            padding:.85rem 1.1rem;

            font-weight:700;

            transition:.25s;
        }

        .btn-primary{

            background:var(--olive-700);

            color:#fff;
        }

        .btn-primary:hover{

            background:var(--olive-900);
        }

        .btn-ghost{

            background:#fff;

            border:1px solid var(--border);
        }

        .btn-ghost:hover{

            background:#f5f5f4;
        }

        .modal{

            position:fixed;

            inset:0;

            background:rgba(0,0,0,.45);

            backdrop-filter:blur(6px);

            display:none;

            align-items:center;

            justify-content:center;

            z-index:999;
        }

        .modal.show{
            display:flex;
        }

        .modal-box{

            background:#fff;

            width:100%;

            max-width:500px;

            border-radius:24px;

            overflow:hidden;
        }

        .modal-head,
        .modal-foot{

            padding:1.25rem;

            border-bottom:1px solid var(--border);
        }

        .modal-foot{

            border-bottom:none;

            border-top:1px solid var(--border);

            display:flex;

            justify-content:flex-end;

            gap:.75rem;
        }

        .modal-body{

            padding:1.25rem;
        }

        .fc{

            width:100%;

            border:1px solid var(--border);

            border-radius:12px;

            padding:1rem;

            font-family:inherit;
        }

        .pw{
            width:min(1400px,100%);
            margin:auto;
        }

        .ph{
            margin-bottom:2rem;
        }

        .ph h1{

            display:flex;

            align-items:center;

            gap:.75rem;

            font-size:2rem;

            font-weight:800;
        }

        .ph h1 svg{

            width:28px;
            height:28px;

            stroke:var(--olive-500);

            fill:none;

            stroke-width:2;
        }

        .charts-grid{

            display:grid;

            gap:1.5rem;
        }

        .anim{

            animation:fadeIn .6s ease both;
        }

        .anim-d1{animation-delay:.1s}
        .anim-d2{animation-delay:.2s}
        .anim-d3{animation-delay:.3s}
        .anim-d4{animation-delay:.4s}
        .anim-d5{animation-delay:.5s}

        @keyframes fadeIn{

            from{
                opacity:0;
                transform:translateY(20px);
            }

            to{
                opacity:1;
                transform:none;
            }
        }

        @media(max-width:900px){

            .sidebar{

                width:100%;

                height:auto;

                position:relative;
            }

            .main{

                margin-left:0;
            }
        }

    </style>

</head>

<body>

<?php
}

function rattil_sidebar($user,$active='dashboard',$notifs=0){
?>

<div class="sidebar">

    <div class="brand">
        رتّل
    </div>

    <a
            class="<?= $active==='dashboard'?'active':'' ?>"
            href="/rattil/student/dashboard.php"
    >
        الرئيسية
    </a>

    <a
            class="<?= $active==='halaqat'?'active':'' ?>"
            href="/rattil/student/halaqat.php"
    >
        حلقاتي
    </a>

    <a href="#">
        الرسائل
    </a>

</div>

<div class="main">

    <?php
    }

    function rattil_modals_js(){
        ?>

        <script>

            function openModal(id){

                document
                    .getElementById(id)
                    .classList
                    .add('show');
            }

            function closeModal(id){

                document
                    .getElementById(id)
                    .classList
                    .remove('show');
            }

        </script>

        <?php
    }
    ?>

