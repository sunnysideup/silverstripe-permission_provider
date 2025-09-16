<!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Invitation</title>

</head>

<body>
    Hi $FirstName,

    <% if $IsNew %>
        We have created a login for you on: $AbsoluteUrl.
        You can <a href="$Link">reset your password</a> to create your own login.
    <% else %>
        We have reset your password on: $AbsoluteUrl.
    You can <a href="$Link">set your own password</a> to login again.
    <% end_if %>
</body>
</html>
