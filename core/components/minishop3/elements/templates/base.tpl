<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MiniShop3 - {$_modx->resource.pagetitle}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>

<div class="container">
   <header>
       <div class="accordion" id="headerAccordion">
           <div class="accordion-item">
               <h2 class="accordion-header">
                   <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#headerMiniCart" aria-expanded="false" aria-controls="headerMiniCart">
                       Мини-корзина
                   </button>
               </h2>
               <div id="headerMiniCart" class="accordion-collapse collapse" data-bs-parent="#headerAccordion">
                   {'!msCart'|snippet:[
                       'tpl' => 'tpl.msMiniCart',
                       'selector' => '#headerMiniCart'
                   ]}
               </div>
           </div>

       </div>

   </header>
</div>

{block 'pagecontent'}{/block}


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
