<?php
    require_once "misc/header.php";

    // Feel free to add your donation options here, but please don't remove mine.
?>

    <title>LibreZ- Donate</title>
    </head>
    <body>
    <div class="donate-container">
      <!-- librex dev -->
      <h2>
<?php printftext("donate_original_developer", "Libre<span class=\"Y\">X</span>")?>
      </h2>

      <div class="flexbox-column">
        <div class="qr-box">
          <div class="inner-wrap">
            <h3>Bitcoin [BTC]</h3>
            <p>bc1qs43kh6tvhch02dtsp7x7hcrwj8fwe4rzy7lp0h</p>
          </div>

          <img
            src="static/images/btc.png"
            height="160"
            width="160"
            alt="btc qr code"
          />
        </div>
        <div class="qr-box">
          <div class="inner-wrap">
            <h3>Monero [XMR]</h3>
            <p>
              41dGQr9EwZBfYBY3fibTtJZYfssfRuzJZDSVDeneoVcgckehK3BiLxAV4FvEVJiVqdiW996zvMxhFB8G8ot9nBFqQ84VkuC
            </p>
          </div>
          <img
            src="static/images/xmr.png"
            height="160"
            width="160"
            alt="xmr qr code (hnhx)"
          />
        </div>

        <hr class="small-line" />

        <!-- librey dev -->
      <h2>
<?php printftext("donate_fork", "Libre<span class=\"Y\">Z</span>")?>
      </h2>

        <div class="qr-box">
          <div class="inner-wrap">
            <h3>NO donations atm</h3>
              <p>
              </p>
          </div>
          <!--<img
            src="static/images/xmr-ahwx.png"
            height="160"
            width="160"
            alt="xmr qr code (ahwx)"
          />-->
        </div>
          
        <div class="flex-row">
          <a href="https://ko-fi.com/Ahwxorg" target="_blank"
            ><img
              src="static/images/kofi.png"
              alt="kifi img"
              height="50"
              width="auto"
          /></a>

          <a href="https://www.buymeacoffee.com/ahwx" target="_blank">
            <img
              src="static/images/buy-me-a-coffee.png"
              height="50"
              width="auto"
              alt="buy-me-a-coffee img"
          /></a>
        </div>
      </div>
    </div>

        

<?php require_once "misc/footer.php"; ?>
