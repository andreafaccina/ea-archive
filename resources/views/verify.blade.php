@extends('layout')
@section('content')<div class="auth panel"><div class="eyebrow">UN ULTIMO PASSAGGIO</div><h1>Verifica la tua email.</h1><p>Apri il link che ti abbiamo inviato prima di collegare un conto, caricare sorgenti o compilare gli EA.</p><form method="post" action="/email/verification-notification">@csrf<button class="button">Invia di nuovo il link →</button></form></div>@endsection
