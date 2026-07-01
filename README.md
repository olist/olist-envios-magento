# Envios da Olist — Módulo Magento 2

Módulo oficial de fretes para Magento 2. Exibe as opções de frete do Envios da Olist diretamente no checkout da loja, consumindo a API de cotação em tempo real.

---

## Requisitos

- PHP 8.1 ou superior
- Magento 2.4.x
- Extensão PHP `ext-curl`
- Conta ativa no [Envios da Olist](https://envios.olist.com) com token de integração gerado

---

## Instalação via Composer

```bash
composer require olist/module-envios
php bin/magento module:enable Olist_Envios
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Instalação manual

1. Faça o download ou clone este repositório
2. Copie a pasta para `app/code/Olist/Envios/` na raiz da sua instalação Magento
3. Execute os comandos abaixo:

```bash
php bin/magento module:enable Olist_Envios
php bin/magento setup:upgrade
php bin/magento cache:flush
```

---

## Configuração

Acesse **Lojas → Configuração → Vendas → Métodos de Entrega → Envios da Olist** e preencha os campos:

| Campo | Descrição |
|---|---|
| **Habilitado** | Ativa ou desativa o método de entrega no checkout |
| **Título** | Nome exibido ao comprador (padrão: "Envios da Olist") |
| **Token de integração** | UUID do token obtido no painel do Envios da Olist |
| **URL da API** | URL base da API — altere apenas para apontar a um ambiente de testes |
| **Mensagem de erro** | Texto exibido ao comprador quando o cálculo de frete falhar |
| **Modo debug** | Registra requisições e respostas em `var/log/system.log` |

Salve e limpe o cache:

```bash
php bin/magento cache:flush
```

---

## Dimensões de produtos

Altura, largura e comprimento não são atributos nativos do Magento, então o módulo envia o valor padrão de **10 cm** para cada dimensão de item. Não é necessário configurar medidas nos produtos da loja.

---

## Unidade de peso

O módulo lê a unidade de peso configurada na loja em **Lojas → Configuração → Geral → Opções de Localidade → Unidade de Peso** e converte automaticamente para quilogramas antes de enviar à API.

Unidades suportadas: `kg` (sem conversão) e `lbs` (multiplicado por 0,453592).

---

## Cache

As cotações são armazenadas em cache por **5 minutos** com base no conteúdo do carrinho e no CEP de destino. Para limpar o cache de cotações manualmente:

```bash
php bin/magento cache:clean olist_envios_quote
```

---

## Debug

Ative o **Modo debug** nas configurações. As requisições e respostas da API serão registradas em `var/log/system.log` com o prefixo `[Olist Envios]`.

---

## Ambiente de desenvolvimento local

O repositório inclui um ambiente Docker em `development/` com Magento, banco de dados e OpenSearch prontos para uso. O código-fonte do Magento fica armazenado em um volume interno do Docker — não é necessário baixá-lo manualmente.

### Pré-requisitos

- Docker e Docker Compose instalados
- Chaves de acesso do [Magento Marketplace](https://marketplace.magento.com) configuradas em `~/.composer/auth.json`:

```json
{
    "http-basic": {
        "repo.magento.com": {
            "username": "SUA_CHAVE_PUBLICA",
            "password": "SUA_CHAVE_PRIVADA"
        }
    }
}
```

### Uso

```bash
make dev-up    # inicia os containers (na primeira vez, baixa e instala o Magento automaticamente)
make dev-stop  # pausa os containers preservando o estado (reinício rápido com dev-up)
make dev-down  # remove os containers e redes (dados nos volumes são preservados)
make dev-shell # abre um terminal dentro do container
make dev-logs  # acompanha o progresso da instalação automática
```

Use `dev-stop` / `dev-up` no dia a dia — é mais rápido pois os containers não precisam ser recriados. Use `dev-down` quando quiser liberar recursos ou após uma sessão mais longa. Se o arquivo `docker-compose.yml` for alterado, é necessário recriar os containers:

```bash
docker compose -f development/docker-compose.yml up -d --force-recreate magento
```

### Apontando para uma API local

O container do Magento não consegue acessar `localhost` da máquina host diretamente. O `docker-compose.yml` já está configurado com `extra_hosts: host.docker.internal:host-gateway`, que mapeia automaticamente o hostname `host.docker.internal` para o IP da máquina host.

Para apontar o módulo para uma API rodando localmente (ex.: `http://localhost:5000`), acesse o painel admin em **http://localhost/admin** e configure a **URL da API** como:

```
http://host.docker.internal:5000
```

Ou via CLI dentro do container:

```bash
bin/magento config:set carriers/olist_envios/api_url http://host.docker.internal:5000
bin/magento cache:flush
```

Na **primeira execução**, `make dev-up` dispara um container de inicialização que baixa o Magento 2.4.8, executa a instalação e habilita o módulo `Olist_Envios`. Isso pode levar alguns minutos. Nas execuções seguintes o ambiente sobe instantaneamente.

Acesse em **http://localhost** e o painel admin em **http://localhost/admin** (usuário `admin` / senha `Admin1234!`).

---

## Desinstalação

```bash
php bin/magento module:disable Olist_Envios
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Se instalado via Composer:

```bash
composer remove olist/module-envios
```
