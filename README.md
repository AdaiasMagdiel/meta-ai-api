Segue a versão do README com a seção de instalação adicionada:

---

# PHP MetaAI API Wrapper

MetaAI is a PHP library designed to seamlessly interact with Meta's AI APIs, which power the backend of [meta.ai](https://www.meta.ai/). This library simplifies the complexities of authentication and communication, offering a user-friendly interface for sending queries and receiving responses from Meta AI.

This project is inspired by and builds upon the excellent work of [Strvm](https://github.com/Strvm/meta-ai-api/), originally implemented in Python.

With **MetaAI**, you can effortlessly prompt the AI with a message and receive real-time responses directly in your PHP applications.

**No API key is required.**

Key Features:
 - **Internet-connected AI**: MetaAI leverages real-time capabilities (powered by Bing), enabling you to receive up-to-date responses.
 - **Powered by Llama 3 LLM**: Utilizes Meta's latest **Llama 3** large language model for high-quality responses.

> [!IMPORTANT]
> This project does not yet support image generation.

## Installation

You can install the library via Composer. Run the following command:

```bash
composer require adaiasmagdiel/meta-ai-api
```

Once installed, you can start using the library in your project.

```php
require_once __DIR__ . "/vendor/autoload.php";

use AdaiasMagdiel\MetaAI\Client;

$client = new Client();
$res = $client->prompt("Hi! What's the date and weather in Itaituba, Pará, today?");

echo $res->message . PHP_EOL;
```

## License

This project is licensed under the terms of the [GNU General Public License v3](LICENSE).

## Copyright

```
AdaiasMagdiel/meta-ai-api: A reverse-engineered API wrapper for MetaAI in PHP, based on the work of [Strvm](https://github.com/Strvm/meta-ai-api/)

Copyright (C) 2024 Adaías Magdiel

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
```

## Additional Licensing Information

For detailed information about licensing terms specific to Llama 3, please refer to Meta’s official [Llama 3 License](https://www.llama.com/llama3/license/). 
