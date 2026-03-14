<?php
// includes/header.php
// Usage: renderHeader($title)
function renderHeader($title = 'NBSC Feedback') {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . htmlspecialchars($title) . ' — NBSC</title>
  <link rel="stylesheet" href="' . BASE_URL . '/assets/css/style.css">
</head>
<body>';
}