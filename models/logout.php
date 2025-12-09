<?php
    include('../connexion/connexion.php');
    session_destroy();
    header("Location:../index.php");