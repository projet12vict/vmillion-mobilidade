-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Tempo de geração: 13-Ago-2026 às 00:52
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `kabugo_v2`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `administradores`
--

CREATE TABLE `administradores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `nivel` enum('gestor','admin','super') NOT NULL DEFAULT 'admin',
  `senha_temporaria` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `tentativas_login` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `administradores`
--

INSERT INTO `administradores` (`id`, `nome`, `email`, `senha_hash`, `nivel`, `senha_temporaria`, `ativo`, `tentativas_login`, `bloqueado_ate`, `criado_por`, `criado_em`) VALUES
(2, 'Victor Allissson', 'victorallissson@gmail.com', '$2y$10$MR8UDxZhQ7lgtxQDEQxFoeu53GSTD4ueXN90mQ3RrMwAOC2y5dr1O', 'super', 0, 1, 0, NULL, NULL, '2026-08-04 19:33:16');

-- --------------------------------------------------------

--
-- Estrutura da tabela `alarmes_sos`
--

CREATE TABLE `alarmes_sos` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED NOT NULL,
  `tipo_utilizador` enum('passageiro','condutor') NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lng` decimal(9,6) NOT NULL,
  `estado` enum('pendente','em_curso','resolvido') NOT NULL DEFAULT 'pendente',
  `resolvido_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `assentos_veiculo`
--

CREATE TABLE `assentos_veiculo` (
  `id` int(10) UNSIGNED NOT NULL,
  `veiculo_id` int(10) UNSIGNED NOT NULL,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `fila` tinyint(3) UNSIGNED NOT NULL,
  `coluna` tinyint(3) UNSIGNED NOT NULL,
  `ocupado` tinyint(1) NOT NULL DEFAULT 0,
  `reserva_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `assentos_veiculo`
--

INSERT INTO `assentos_veiculo` (`id`, `veiculo_id`, `numero`, `fila`, `coluna`, `ocupado`, `reserva_id`) VALUES
(15, 2, 1, 0, 1, 0, NULL),
(16, 2, 2, 0, 2, 0, NULL),
(17, 2, 3, 1, 1, 0, NULL),
(18, 2, 4, 1, 2, 0, NULL),
(19, 2, 5, 1, 3, 0, NULL),
(20, 2, 6, 2, 1, 0, NULL),
(21, 2, 7, 2, 2, 0, NULL),
(22, 2, 8, 2, 3, 0, NULL),
(23, 2, 9, 3, 1, 0, NULL),
(24, 2, 10, 3, 2, 0, NULL),
(25, 2, 11, 3, 3, 0, NULL),
(26, 2, 12, 4, 1, 0, NULL),
(27, 2, 13, 4, 2, 0, NULL),
(28, 2, 14, 4, 3, 0, NULL),
(57, 6, 1, 0, 1, 0, NULL),
(58, 6, 2, 0, 2, 0, NULL),
(59, 6, 3, 1, 1, 0, NULL),
(60, 6, 4, 1, 2, 0, NULL),
(61, 6, 5, 1, 3, 0, NULL),
(62, 6, 6, 2, 1, 0, NULL),
(63, 6, 7, 2, 2, 0, NULL),
(64, 6, 8, 2, 3, 0, NULL),
(65, 6, 9, 3, 1, 0, NULL),
(66, 6, 10, 3, 2, 0, NULL),
(67, 6, 11, 3, 3, 0, NULL),
(68, 6, 12, 4, 1, 0, NULL),
(69, 6, 13, 4, 2, 0, NULL),
(70, 6, 14, 4, 3, 0, NULL),
(71, 7, 1, 0, 1, 0, NULL),
(72, 7, 2, 0, 2, 0, NULL),
(73, 7, 3, 1, 1, 0, NULL),
(74, 7, 4, 1, 2, 0, NULL),
(75, 7, 5, 1, 3, 0, NULL),
(76, 7, 6, 2, 1, 0, NULL),
(77, 7, 7, 2, 2, 0, NULL),
(78, 7, 8, 2, 3, 0, NULL),
(79, 7, 9, 3, 1, 0, NULL),
(80, 7, 10, 3, 2, 0, NULL),
(81, 7, 11, 3, 3, 0, NULL),
(82, 7, 12, 4, 1, 0, NULL),
(83, 7, 13, 4, 2, 0, NULL),
(84, 7, 14, 4, 3, 0, NULL),
(85, 8, 1, 0, 1, 0, NULL),
(86, 8, 2, 0, 2, 0, NULL),
(87, 8, 3, 1, 1, 0, NULL),
(88, 8, 4, 1, 2, 0, NULL),
(89, 8, 5, 1, 3, 0, NULL),
(90, 8, 6, 2, 1, 0, NULL),
(91, 8, 7, 2, 2, 0, NULL),
(92, 8, 8, 2, 3, 0, NULL),
(93, 8, 9, 3, 1, 0, NULL),
(94, 8, 10, 3, 2, 0, NULL),
(95, 8, 11, 3, 3, 0, NULL),
(96, 8, 12, 4, 1, 0, NULL),
(97, 8, 13, 4, 2, 0, NULL),
(98, 8, 14, 4, 3, 0, NULL),
(102, 14, 1, 0, 1, 0, NULL),
(103, 14, 2, 0, 2, 0, NULL),
(104, 14, 3, 1, 1, 0, NULL),
(105, 14, 4, 1, 2, 0, NULL),
(106, 14, 5, 1, 3, 0, NULL),
(107, 14, 6, 2, 1, 0, NULL),
(108, 14, 7, 2, 2, 0, NULL),
(109, 14, 8, 2, 3, 0, NULL),
(110, 14, 9, 3, 1, 0, NULL),
(111, 14, 10, 3, 2, 0, NULL),
(112, 14, 11, 3, 3, 0, NULL),
(113, 14, 12, 4, 1, 0, NULL),
(114, 14, 13, 4, 2, 0, NULL),
(115, 14, 14, 4, 3, 0, NULL),
(130, 18, 1, 0, 1, 1, 37),
(131, 18, 2, 0, 2, 0, NULL),
(132, 18, 3, 1, 1, 0, NULL),
(133, 18, 4, 1, 2, 0, NULL),
(134, 18, 5, 1, 3, 0, NULL),
(135, 18, 6, 2, 1, 0, NULL),
(136, 18, 7, 2, 2, 0, NULL),
(137, 18, 8, 2, 3, 0, NULL),
(138, 18, 9, 3, 1, 0, NULL),
(139, 18, 10, 3, 2, 0, NULL),
(140, 18, 11, 3, 3, 0, NULL),
(141, 18, 12, 4, 1, 0, NULL),
(142, 18, 13, 4, 2, 0, NULL),
(143, 18, 14, 4, 3, 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `avaliacoes_condutores`
--

CREATE TABLE `avaliacoes_condutores` (
  `id` int(10) UNSIGNED NOT NULL,
  `condutor_id` int(10) UNSIGNED NOT NULL,
  `passageiro_id` int(10) UNSIGNED NOT NULL,
  `reserva_id` int(10) UNSIGNED NOT NULL,
  `avaliacao` tinyint(3) UNSIGNED NOT NULL,
  `comentario` varchar(500) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Extraindo dados da tabela `avaliacoes_condutores`
--

INSERT INTO `avaliacoes_condutores` (`id`, `condutor_id`, `passageiro_id`, `reserva_id`, `avaliacao`, `comentario`, `criado_em`) VALUES
(3, 16, 14, 8, 5, 'Gostei', '2026-08-09 20:05:53'),
(4, 16, 14, 15, 3, 'ok', '2026-08-09 21:48:04'),
(8, 16, 14, 16, 2, NULL, '2026-08-09 21:59:49'),
(11, 16, 14, 17, 3, NULL, '2026-08-09 23:06:53'),
(12, 16, 14, 19, 3, NULL, '2026-08-09 23:09:11'),
(13, 16, 14, 20, 3, NULL, '2026-08-09 23:18:47'),
(14, 16, 14, 21, 4, NULL, '2026-08-09 23:21:02'),
(15, 16, 14, 22, 4, NULL, '2026-08-09 23:43:16'),
(16, 16, 14, 24, 4, NULL, '2026-08-10 19:08:13'),
(17, 16, 14, 32, 3, NULL, '2026-08-10 21:48:00'),
(18, 3, 13, 5, 3, NULL, '2026-08-10 21:52:57');

-- --------------------------------------------------------

--
-- Estrutura da tabela `chamadas`
--

CREATE TABLE `chamadas` (
  `id` int(10) UNSIGNED NOT NULL,
  `remetente_id` int(10) UNSIGNED NOT NULL,
  `destinatario_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('iniciada','atendida','recusada','terminada') NOT NULL DEFAULT 'iniciada',
  `iniciada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atendida_em` datetime DEFAULT NULL,
  `terminada_em` datetime DEFAULT NULL,
  `atualizada_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `chamadas`
--

INSERT INTO `chamadas` (`id`, `remetente_id`, `destinatario_id`, `estado`, `iniciada_em`, `atendida_em`, `terminada_em`, `atualizada_em`) VALUES
(5, 16, 14, 'terminada', '2026-08-10 15:48:53', NULL, '2026-08-10 15:49:48', '2026-08-10 15:49:48'),
(6, 14, 16, 'terminada', '2026-08-10 15:50:51', '2026-08-10 15:51:00', '2026-08-10 15:51:15', '2026-08-10 15:51:15'),
(7, 16, 14, 'terminada', '2026-08-10 15:51:23', '2026-08-10 15:51:28', '2026-08-10 15:52:05', '2026-08-10 15:52:05'),
(9, 16, 14, 'terminada', '2026-08-10 16:25:30', '2026-08-10 16:25:42', '2026-08-10 16:26:03', '2026-08-10 16:26:03'),
(10, 16, 14, 'terminada', '2026-08-10 16:26:23', '2026-08-10 16:26:33', '2026-08-10 16:27:26', '2026-08-10 16:27:26'),
(11, 14, 16, 'terminada', '2026-08-10 19:01:58', '2026-08-10 19:02:10', '2026-08-10 19:07:27', '2026-08-10 19:07:27'),
(12, 16, 14, 'terminada', '2026-08-10 19:43:14', '2026-08-10 19:43:25', '2026-08-10 19:45:45', '2026-08-10 19:45:45'),
(13, 14, 16, 'terminada', '2026-08-10 19:45:48', NULL, '2026-08-10 19:46:30', '2026-08-10 19:46:30'),
(14, 16, 14, 'terminada', '2026-08-10 19:48:56', '2026-08-10 19:49:02', '2026-08-10 19:49:29', '2026-08-10 19:49:29'),
(15, 14, 16, 'terminada', '2026-08-10 19:49:31', '2026-08-10 19:49:37', '2026-08-10 19:50:00', '2026-08-10 19:50:00'),
(16, 14, 16, 'terminada', '2026-08-10 20:30:26', '2026-08-10 20:31:16', '2026-08-10 20:31:30', '2026-08-10 20:31:30'),
(17, 16, 14, 'terminada', '2026-08-10 20:31:46', '2026-08-10 20:32:03', '2026-08-10 20:32:16', '2026-08-10 20:32:16'),
(18, 14, 16, 'terminada', '2026-08-10 21:01:28', NULL, '2026-08-10 21:02:26', '2026-08-10 21:02:26'),
(20, 12, 14, 'recusada', '2026-08-11 09:53:40', NULL, '2026-08-11 09:54:03', '2026-08-11 09:54:03'),
(21, 12, 14, 'terminada', '2026-08-11 10:36:56', NULL, '2026-08-11 10:37:14', '2026-08-11 10:37:14');

-- --------------------------------------------------------

--
-- Estrutura da tabela `comprovativos`
--

CREATE TABLE `comprovativos` (
  `id` int(10) UNSIGNED NOT NULL,
  `condutor_id` int(10) UNSIGNED NOT NULL,
  `ficheiro_path` varchar(255) NOT NULL,
  `valor_cve` decimal(10,2) DEFAULT NULL,
  `estado` enum('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
  `revisto_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `revisto_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `comprovativos`
--

INSERT INTO `comprovativos` (`id`, `condutor_id`, `ficheiro_path`, `valor_cve`, `estado`, `revisto_por`, `criado_em`, `revisto_em`) VALUES
(2, 12, 'manual', 500.00, 'aprovado', 2, '2026-08-08 23:09:28', '2026-08-08 23:09:36'),
(3, 12, 'manual', 500.00, 'aprovado', 2, '2026-08-09 08:41:22', '2026-08-09 08:41:25'),
(4, 16, 'manual', 500.00, 'aprovado', 2, '2026-08-09 19:59:52', '2026-08-09 19:59:54');

-- --------------------------------------------------------

--
-- Estrutura da tabela `comunicacoes_veiculo`
--

CREATE TABLE `comunicacoes_veiculo` (
  `id` int(10) UNSIGNED NOT NULL,
  `veiculo_id` int(10) UNSIGNED NOT NULL,
  `remetente_id` int(10) UNSIGNED NOT NULL,
  `destinatario_id` int(10) UNSIGNED DEFAULT NULL,
  `mensagem` varchar(500) NOT NULL,
  `tipo` enum('texto','sistema') NOT NULL DEFAULT 'texto',
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `comunicacoes_veiculo`
--

INSERT INTO `comunicacoes_veiculo` (`id`, `veiculo_id`, `remetente_id`, `destinatario_id`, `mensagem`, `tipo`, `lida`, `criado_em`) VALUES
(3, 2, 13, 3, 'oi', 'texto', 1, '2026-08-09 17:04:37'),
(4, 2, 3, 13, 'undi k bu sta', 'texto', 1, '2026-08-09 17:05:12'),
(5, 2, 13, 3, 'nsta li na Txada', 'texto', 1, '2026-08-09 17:05:35'),
(7, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 20:01:47'),
(8, 8, 14, 16, 'oi', 'texto', 1, '2026-08-09 20:04:43'),
(9, 8, 16, 14, 'djan djiga', 'texto', 1, '2026-08-09 20:05:07'),
(11, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 20:26:50'),
(12, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 20:30:47'),
(13, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 21:03:04'),
(14, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 21:44:52'),
(15, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 21:49:19'),
(16, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 22:18:20'),
(17, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 23:08:35'),
(18, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 23:18:15'),
(19, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 23:20:27'),
(20, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-09 23:21:25'),
(21, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-10 00:02:07'),
(22, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-10 19:11:26'),
(23, 8, 16, 14, '🚗 Blaika Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-10 20:55:46'),
(24, 18, 12, 14, '🚗 Claudina Semedo está a caminho para o(a) buscar.', 'sistema', 1, '2026-08-11 09:53:03'),
(25, 18, 14, 12, 'Bom dia', 'texto', 1, '2026-08-11 09:56:15'),
(26, 18, 14, 12, 'Mi nsta li na txada santo António', 'texto', 1, '2026-08-11 09:56:29');

-- --------------------------------------------------------

--
-- Estrutura da tabela `config_precos`
--

CREATE TABLE `config_precos` (
  `id` int(10) UNSIGNED NOT NULL,
  `chave` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `atualizado_por` int(10) UNSIGNED DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `config_precos`
--

INSERT INTO `config_precos` (`id`, `chave`, `valor`, `descricao`, `atualizado_por`, `atualizado_em`) VALUES
(1, 'valor_minimo', 100.00, 'Valor mínimo da viagem (CVE)', NULL, '2026-08-08 17:55:30'),
(2, 'valor_maximo', 5000.00, 'Valor máximo da viagem (CVE)', NULL, '2026-08-08 17:55:30'),
(3, 'taxa_operacao_rota', 50.00, 'Taxa que o condutor paga por rota completa (ida e volta), em CVE', NULL, '2026-08-08 17:52:31');

-- --------------------------------------------------------

--
-- Estrutura da tabela `destinos_urbanos`
--

CREATE TABLE `destinos_urbanos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(255) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lng` decimal(9,6) NOT NULL,
  `criado_por` int(10) UNSIGNED NOT NULL,
  `usos` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `destinos_urbanos`
--

INSERT INTO `destinos_urbanos` (`id`, `nome`, `lat`, `lng`, `criado_por`, `usos`, `criado_em`, `atualizado_em`) VALUES
(2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14, 5, '2026-08-09 23:08:16', '2026-08-10 00:01:32'),
(9, 'Praia Senhora de Encarnação, Alto de Santa Luzia, Nossa Senhora da Conceição, São Filipe, 8220, Cabo Verde', 14.878261, -24.487872, 14, 1, '2026-08-10 19:09:34', '2026-08-10 19:09:34'),
(10, 'São Filipe de Baixo, Praia, Agostinho Alvies, Praia, 7601, Cabo Verde', 14.954089, -23.514624, 14, 2, '2026-08-10 19:10:59', '2026-08-10 20:52:49'),
(11, 'São Miguel, Assomada-Calheta, Lem Gomes, Calheta de São Miguel, São Miguel, 7215, Cabo Verde', 15.180364, -23.637740, 14, 1, '2026-08-10 20:41:52', '2026-08-10 20:41:52'),
(13, 'Plateau Banco Interatlantico, Avenida Amilcar Cabral, Platô, Praia, Platô, Praia, 7600, Cabo Verde', 14.918170, -23.509302, 14, 1, '2026-08-11 09:20:01', '2026-08-11 09:20:01');

-- --------------------------------------------------------

--
-- Estrutura da tabela `faturas`
--

CREATE TABLE `faturas` (
  `id` int(10) UNSIGNED NOT NULL,
  `condutor_id` int(10) UNSIGNED NOT NULL,
  `referencia` varchar(50) NOT NULL,
  `valor_cve` decimal(10,2) NOT NULL,
  `estado` enum('pendente','paga','vencida') NOT NULL DEFAULT 'pendente',
  `vencimento` date NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `limites_cidades`
--

CREATE TABLE `limites_cidades` (
  `id` int(10) UNSIGNED NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lng` decimal(9,6) NOT NULL,
  `raio_km` decimal(6,2) NOT NULL,
  `atualizado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `limites_cidades`
--

INSERT INTO `limites_cidades` (`id`, `cidade`, `lat`, `lng`, `raio_km`, `atualizado_por`, `criado_em`, `atualizado_em`) VALUES
(1, 'Praia', 14.917700, -23.509200, 6.00, NULL, '2026-08-08 17:52:31', '2026-08-08 17:52:31'),
(2, 'Santa Catarina', 15.098300, -23.670300, 4.00, NULL, '2026-08-08 17:52:31', '2026-08-08 17:52:31'),
(3, 'Tarrafal', 15.278500, -23.751900, 3.50, NULL, '2026-08-08 17:52:31', '2026-08-08 17:52:31'),
(4, 'Ribeira Grande de Santiago', 14.915300, -23.604700, 3.00, NULL, '2026-08-08 17:52:31', '2026-08-08 17:52:31');

-- --------------------------------------------------------

--
-- Estrutura da tabela `logs_auditoria`
--

CREATE TABLE `logs_auditoria` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `entidade` varchar(60) DEFAULT NULL,
  `entidade_id` int(10) UNSIGNED DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `logs_auditoria`
--

INSERT INTO `logs_auditoria` (`id`, `admin_id`, `acao`, `entidade`, `entidade_id`, `detalhes`, `ip`, `criado_em`) VALUES
(1, 2, 'login', 'administradores', 1, NULL, '127.0.0.1', '2026-08-07 14:05:31'),
(2, 2, 'trocou_senha', 'administradores', 1, NULL, '127.0.0.1', '2026-08-07 14:05:48'),
(3, 2, 'registou_comprovativo', 'comprovativos', 1, 'condutor #2', '127.0.0.1', '2026-08-07 14:20:34'),
(4, 2, 'comprovativo_aprovado', 'comprovativos', 1, NULL, '127.0.0.1', '2026-08-07 14:20:36'),
(5, 2, 'aprovou_condutor', 'utilizadores', 2, NULL, '127.0.0.1', '2026-08-07 14:20:37'),
(6, 2, 'aprovou_veiculo', 'veiculos', 1, NULL, '127.0.0.1', '2026-08-07 14:21:44'),
(7, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-07 15:18:35'),
(8, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-07 15:19:57'),
(13, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-08 22:41:19'),
(14, 2, 'registou_comprovativo', 'comprovativos', 2, 'condutor #12', '::1', '2026-08-08 23:09:28'),
(15, 2, 'comprovativo_aprovado', 'comprovativos', 2, NULL, '::1', '2026-08-08 23:09:36'),
(16, 2, 'aprovou_condutor', 'utilizadores', 12, NULL, '::1', '2026-08-08 23:09:43'),
(17, 2, 'aprovou_veiculo', 'veiculos', 6, NULL, '::1', '2026-08-08 23:32:31'),
(18, 2, 'aprovou_pagamento_condutor', 'pagamentos_condutores', 2, 'KG20260808-76A878', '::1', '2026-08-09 00:17:46'),
(19, 2, 'aprovou_veiculo', 'veiculos', 7, NULL, '::1', '2026-08-09 00:29:46'),
(20, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-09 08:19:15'),
(21, 2, 'enviou_notificacao', 'notificacoes', NULL, 'condutores: Rotas (Calheta) para 2 destinatário(s)', '::1', '2026-08-09 08:37:12'),
(22, 2, 'registou_comprovativo', 'comprovativos', 3, 'condutor #12', '::1', '2026-08-09 08:41:22'),
(23, 2, 'comprovativo_aprovado', 'comprovativos', 3, NULL, '::1', '2026-08-09 08:41:25'),
(24, 2, 'aprovou_pagamento_condutor', 'pagamentos_condutores', 3, 'KG20260809-3DD542', '::1', '2026-08-09 09:26:22'),
(25, 2, 'desativou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 12:01:58'),
(26, 2, 'desativou_ponto', 'pontos_partida', 6, NULL, '::1', '2026-08-09 12:02:00'),
(27, 2, 'desativou_ponto', 'pontos_partida', 6, NULL, '::1', '2026-08-09 12:02:01'),
(28, 2, 'desativou_ponto', 'pontos_partida', 6, NULL, '::1', '2026-08-09 12:02:02'),
(29, 2, 'desativou_ponto', 'pontos_partida', 6, NULL, '::1', '2026-08-09 12:02:02'),
(30, 2, 'desativou_ponto', 'pontos_partida', 5, NULL, '::1', '2026-08-09 12:02:04'),
(31, 2, 'desativou_ponto', 'pontos_partida', 2, NULL, '::1', '2026-08-09 12:02:04'),
(32, 2, 'desativou_ponto', 'pontos_partida', 1, NULL, '::1', '2026-08-09 12:02:05'),
(33, 2, 'desativou_ponto', 'pontos_partida', 4, NULL, '::1', '2026-08-09 12:02:06'),
(34, 2, 'desativou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 12:28:12'),
(35, 2, 'desativou_ponto', 'pontos_partida', 6, NULL, '::1', '2026-08-09 12:28:19'),
(36, 2, 'desativou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 13:01:13'),
(37, 2, 'desativou_ponto', 'pontos_partida', 6, NULL, '::1', '2026-08-09 13:01:14'),
(38, 2, 'desativou_ponto', 'pontos_partida', 5, NULL, '::1', '2026-08-09 13:01:15'),
(39, 2, 'editou_ponto', 'pontos_partida', 3, 'Assomada', '::1', '2026-08-09 13:01:19'),
(40, 2, 'desativou_ponto', 'pontos_partida', 1, NULL, '::1', '2026-08-09 13:02:58'),
(41, 2, 'desativou_ponto', 'pontos_partida', 1, NULL, '::1', '2026-08-09 13:03:00'),
(42, 2, 'desativou_ponto', 'pontos_partida', 4, NULL, '::1', '2026-08-09 13:03:01'),
(43, 2, 'desativou_ponto', 'pontos_partida', 4, NULL, '::1', '2026-08-09 13:03:01'),
(44, 2, 'aprovou_ponto', 'pontos_partida', NULL, 'MigraþÒo automßtica: 6 pontos prÚ-existentes (usados no piloto antes do fluxo de aprovaþÒo) marcados aprovado retroativamente.', NULL, '2026-08-09 13:12:37'),
(45, 2, 'recusou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 13:35:41'),
(46, 2, 'aprovou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 13:35:44'),
(47, 2, 'recusou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 13:35:48'),
(48, 2, 'aprovou_ponto', 'pontos_partida', 3, NULL, '::1', '2026-08-09 13:35:50'),
(49, 2, 'recusou_ponto', 'pontos_partida', 1, NULL, '::1', '2026-08-09 13:36:03'),
(50, 2, 'aprovou_ponto', 'pontos_partida', 1, NULL, '::1', '2026-08-09 13:36:14'),
(51, 2, 'moveu_ponto', 'pontos_partida', 1, '14.918796, -23.509423', '::1', '2026-08-09 14:09:40'),
(52, 2, 'moveu_ponto', 'pontos_partida', 1, '14.920424, -23.508929', '::1', '2026-08-09 14:10:17'),
(53, 2, 'moveu_ponto', 'pontos_partida', 2, '14.915821, -23.51115', '::1', '2026-08-09 14:10:34'),
(54, 2, 'moveu_ponto', 'pontos_partida', 2, '14.916106, -23.511241', '::1', '2026-08-09 14:11:30'),
(55, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-09 14:57:05'),
(56, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '::1', '2026-08-09 15:11:15'),
(57, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '::1', '2026-08-09 15:11:19'),
(58, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '::1', '2026-08-09 15:11:24'),
(59, 2, 'corrigiu_ancoragem_veiculos', 'veiculos', NULL, 'MigraþÒo automßtica: veÝculos no_ponto tinham lat/lng desalinhadas do ponto_partida_id (bug anterior Ó correþÒo de veiculo_ponto.php) ù realinhadas Ós coordenadas reais do ponto.', NULL, '2026-08-09 15:43:33'),
(60, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '::1', '2026-08-09 17:58:46'),
(61, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-09 19:53:13'),
(62, 2, 'reativou_utilizador', 'utilizadores', 16, NULL, '::1', '2026-08-09 19:54:52'),
(63, 2, 'aprovou_veiculo', 'veiculos', 8, NULL, '::1', '2026-08-09 19:56:59'),
(64, 2, 'registou_comprovativo', 'comprovativos', 4, 'condutor #16', '::1', '2026-08-09 19:59:52'),
(65, 2, 'comprovativo_aprovado', 'comprovativos', 4, NULL, '::1', '2026-08-09 19:59:54'),
(66, 2, 'aprovou_pagamento_condutor', 'pagamentos_condutores', 4, 'KG20260809-15D889', '::1', '2026-08-09 20:00:44'),
(67, NULL, 'condutor_recolheu_urbano', 'reservas', 8, 'veiculo #8', '::1', '2026-08-09 20:01:47'),
(69, NULL, 'condutor_recolheu_urbano', 'reservas', 10, 'veiculo #8', '::1', '2026-08-09 20:26:50'),
(70, NULL, 'condutor_recolheu_urbano', 'reservas', 11, 'veiculo #8', '::1', '2026-08-09 20:30:47'),
(71, NULL, 'condutor_recolheu_urbano', 'reservas', 13, 'veiculo #8', '::1', '2026-08-09 21:03:04'),
(72, NULL, 'condutor_recolheu_urbano', 'reservas', 15, 'veiculo #8', '::1', '2026-08-09 21:44:52'),
(73, NULL, 'condutor_recolheu_urbano', 'reservas', 16, 'veiculo #8', '::1', '2026-08-09 21:49:19'),
(74, 2, 'aprovou_pagamento_condutor', 'pagamentos_condutores', 6, 'KG20260809-DF2603', '::1', '2026-08-09 22:14:44'),
(75, NULL, 'condutor_recolheu_urbano', 'reservas', 17, 'veiculo #8', '::1', '2026-08-09 22:18:20'),
(76, NULL, 'condutor_recolheu_urbano', 'reservas', 19, 'veiculo #8', '::1', '2026-08-09 23:08:35'),
(77, NULL, 'condutor_recolheu_urbano', 'reservas', 20, 'veiculo #8', '::1', '2026-08-09 23:18:15'),
(78, NULL, 'condutor_recolheu_urbano', 'reservas', 21, 'veiculo #8', '::1', '2026-08-09 23:20:27'),
(79, NULL, 'condutor_recolheu_urbano', 'reservas', 22, 'veiculo #8', '::1', '2026-08-09 23:21:25'),
(80, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '::1', '2026-08-09 23:46:04'),
(81, NULL, 'condutor_recolheu_urbano', 'reservas', 24, 'veiculo #8', '::1', '2026-08-10 00:02:07'),
(82, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-10 08:19:26'),
(83, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '::1', '2026-08-10 10:33:52'),
(84, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-10 16:34:33'),
(85, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-10 18:34:15'),
(86, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-10 19:01:25'),
(87, NULL, 'condutor_recolheu_urbano', 'reservas', 30, 'veiculo #8', '::1', '2026-08-10 19:11:26'),
(88, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=6', '192.168.1.220', '2026-08-10 20:12:19'),
(89, NULL, 'condutor_definiu_ponto', 'veiculos', 2, 'ponto=1 destino=5', '192.168.1.220', '2026-08-10 20:12:35'),
(90, 2, 'rejeitou_veiculo', 'veiculos', 2, NULL, '::1', '2026-08-10 20:13:36'),
(91, 2, 'aprovou_veiculo', 'veiculos', 14, NULL, '::1', '2026-08-10 20:15:27'),
(92, NULL, 'condutor_definiu_ponto', 'veiculos', 14, 'ponto=1 destino=6', '::1', '2026-08-10 20:21:19'),
(93, 2, 'aprovou_pagamento_condutor', 'pagamentos_condutores', 7, 'KG20260810-5E187A', '::1', '2026-08-10 20:54:58'),
(94, NULL, 'condutor_recolheu_urbano', 'reservas', 32, 'veiculo #8', '::1', '2026-08-10 20:55:46'),
(95, NULL, 'condutor_definiu_ponto', 'veiculos', 6, 'ponto=2 destino=6', '192.168.1.146', '2026-08-10 21:55:11'),
(96, NULL, 'condutor_definiu_ponto', 'veiculos', 6, 'ponto=2 destino=6', '192.168.1.146', '2026-08-10 21:55:36'),
(97, NULL, 'condutor_definiu_ponto', 'veiculos', 6, 'ponto=2 destino=6', '192.168.1.146', '2026-08-10 21:55:58'),
(98, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-10 23:46:22'),
(99, NULL, 'condutor_definiu_ponto', 'veiculos', 8, 'ponto=2 destino=3', '::1', '2026-08-10 23:46:48'),
(100, NULL, 'condutor_definiu_ponto', 'veiculos', 8, 'ponto=1 destino=3', '::1', '2026-08-10 23:50:04'),
(101, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-11 07:56:44'),
(102, 2, 'aprovou_veiculo', 'veiculos', 18, NULL, '::1', '2026-08-11 08:03:31'),
(103, NULL, 'condutor_definiu_ponto', 'veiculos', 18, 'ponto=1 destino=6', '::1', '2026-08-11 08:04:11'),
(104, NULL, 'condutor_recolheu_urbano', 'reservas', 37, 'veiculo #18', '::1', '2026-08-11 09:53:03'),
(105, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-11 12:30:07'),
(106, 2, 'aprovou_pagamento_condutor', 'pagamentos_condutores', 11, 'KG20260811-2A47EC', '::1', '2026-08-11 14:47:41'),
(107, 2, 'criou_pacote_pagamento', 'pacotes_pagamento', 5, 'Diario (urbano): 100 CVE / 1d', '::1', '2026-08-11 16:51:18'),
(108, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-11 19:01:57'),
(109, 2, 'login', 'administradores', 2, NULL, '::1', '2026-08-12 04:30:28');

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `destinatario_id` int(10) UNSIGNED DEFAULT NULL,
  `destinatario_tipo` enum('todos','admins','condutores','passageiros','individual') NOT NULL DEFAULT 'individual',
  `remetente_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('alerta','informativo','urgente') NOT NULL DEFAULT 'informativo',
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `destinatario_id`, `destinatario_tipo`, `remetente_id`, `titulo`, `mensagem`, `tipo`, `lida`, `criado_em`) VALUES
(3, 3, 'condutores', 2, 'Rotas', 'Calheta', 'informativo', 1, '2026-08-09 08:37:12'),
(4, 12, 'condutores', 2, 'Rotas', 'Calheta', 'informativo', 1, '2026-08-09 08:37:12'),
(5, 16, 'individual', 2, 'Pagamento aprovado', 'O seu pagamento (ref. KG20260811-2A47EC) foi aprovado. Acesso válido até 2026-08-18 12:47:40.', 'informativo', 1, '2026-08-11 14:47:41'),
(6, 36, 'individual', 2, 'Conta ativada', 'O seu pagamento (ref. TESTE-REF-AUDIT) foi aprovado. A sua conta foi ativada com sucesso.', 'informativo', 0, '2026-08-11 20:34:42');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pacotes_pagamento`
--

CREATE TABLE `pacotes_pagamento` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(20) NOT NULL,
  `tipo_servico` enum('urbano','intermunicipal','ambos') NOT NULL DEFAULT 'ambos',
  `descricao` varchar(255) DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `duracao_dias` int(10) UNSIGNED NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `pacotes_pagamento`
--

INSERT INTO `pacotes_pagamento` (`id`, `nome`, `tipo_servico`, `descricao`, `preco`, `duracao_dias`, `ativo`, `criado_por`, `criado_em`, `atualizado_em`) VALUES
(1, 'diario', 'ambos', 'Acesso por 1 dia', 100.00, 1, 1, NULL, '2026-08-11 13:37:50', '2026-08-11 13:37:50'),
(2, 'semanal', 'ambos', 'Acesso por 7 dias', 500.00, 7, 1, NULL, '2026-08-11 13:37:50', '2026-08-11 13:37:50'),
(3, 'mensal', 'ambos', 'Acesso por 30 dias', 1500.00, 30, 1, NULL, '2026-08-11 13:37:50', '2026-08-11 13:37:50'),
(4, 'anual', 'ambos', 'Acesso por 365 dias', 15000.00, 365, 1, NULL, '2026-08-11 13:37:50', '2026-08-11 13:37:50'),
(5, 'Diario', 'urbano', 'Basico', 100.00, 1, 1, 2, '2026-08-11 16:51:18', '2026-08-11 16:51:18');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pagamentos_condutores`
--

CREATE TABLE `pagamentos_condutores` (
  `id` int(10) UNSIGNED NOT NULL,
  `condutor_id` int(10) UNSIGNED NOT NULL,
  `veiculo_id` int(10) UNSIGNED NOT NULL,
  `rota_id` int(10) UNSIGNED DEFAULT NULL,
  `pacote_id` int(10) UNSIGNED DEFAULT NULL,
  `valor_pago` decimal(10,2) NOT NULL,
  `referencia` varchar(40) NOT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `data_validade` datetime DEFAULT NULL,
  `aprovado_por` int(10) UNSIGNED DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `status` enum('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
  `recibo_path` varchar(255) DEFAULT NULL,
  `comprovativo_path` varchar(255) DEFAULT NULL,
  `comprovativo_tipo` enum('imagem','pdf') DEFAULT NULL,
  `observacao_admin` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `pagamentos_condutores`
--

INSERT INTO `pagamentos_condutores` (`id`, `condutor_id`, `veiculo_id`, `rota_id`, `pacote_id`, `valor_pago`, `referencia`, `data_pagamento`, `data_validade`, `aprovado_por`, `aprovado_em`, `status`, `recibo_path`, `comprovativo_path`, `comprovativo_tipo`, `observacao_admin`, `criado_em`) VALUES
(2, 12, 6, NULL, NULL, 50.00, 'KG20260808-76A878', '2026-08-08 22:17:46', '2026-09-07 22:17:46', 2, NULL, 'aprovado', 'uploads/recibos/recibo_KG20260808-76A878.pdf', NULL, NULL, NULL, '2026-08-08 23:13:20'),
(3, 3, 2, NULL, NULL, 50.00, 'KG20260809-3DD542', '2026-08-09 07:26:22', '2026-09-08 07:26:22', 2, NULL, 'aprovado', 'uploads/recibos/recibo_KG20260809-3DD542.pdf', NULL, NULL, NULL, '2026-08-09 09:25:12'),
(4, 16, 8, NULL, NULL, 50.00, 'KG20260809-15D889', '2026-08-09 18:00:44', '2026-09-08 18:00:44', 2, NULL, 'aprovado', 'uploads/recibos/recibo_KG20260809-15D889.pdf', NULL, NULL, NULL, '2026-08-09 20:00:17'),
(6, 16, 8, NULL, NULL, 50.00, 'KG20260809-DF2603', '2026-08-09 20:14:44', '2026-09-08 20:14:44', 2, NULL, 'aprovado', 'uploads/recibos/recibo_KG20260809-DF2603.pdf', NULL, NULL, NULL, '2026-08-09 20:06:49'),
(7, 16, 8, NULL, NULL, 50.00, 'KG20260810-5E187A', '2026-08-10 18:54:58', '2026-09-09 18:54:58', 2, NULL, 'aprovado', 'uploads/recibos/recibo_KG20260810-5E187A.pdf', NULL, NULL, NULL, '2026-08-10 20:53:45'),
(11, 16, 8, NULL, 2, 500.00, 'KG20260811-2A47EC', '2026-08-11 12:47:40', '2026-08-18 12:47:40', 2, '2026-08-11 14:47:40', 'aprovado', 'uploads/recibos/recibo_KG20260811-2A47EC.pdf', 'uploads/comprovativos/pag_16_1786455999.pdf', 'pdf', NULL, '2026-08-11 14:46:39');

-- --------------------------------------------------------

--
-- Estrutura da tabela `parques`
--

CREATE TABLE `parques` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `morada` varchar(200) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lng` decimal(9,6) NOT NULL,
  `capacidade_total` smallint(5) UNSIGNED NOT NULL,
  `vagas_ocupadas` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Extraindo dados da tabela `parques`
--

INSERT INTO `parques` (`id`, `nome`, `morada`, `cidade`, `lat`, `lng`, `capacidade_total`, `vagas_ocupadas`, `criado_por`, `criado_em`) VALUES
(1, 'Parque de estacionamento - Mercado de Sucupira', 'Junto ao Mercado de Sucupira', 'Praia', 14.921523, -23.508371, 40, 12, 2, '2026-08-05 22:15:12'),
(2, 'Parque de estacionamento - Assomada', 'Junto ao ponto oficial de Santa Catarina, Assomada', 'Santa Catarina', 15.096323, -23.666439, 25, 25, 2, '2026-08-05 22:15:12'),
(3, 'Parque de estacionamento - Tarrafal', 'Junto ao ponto oficial do Tarrafal', 'Tarrafal', 15.275800, -23.751900, 20, 10, 2, '2026-08-05 22:15:13');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pontos_partida`
--

CREATE TABLE `pontos_partida` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lng` decimal(9,6) NOT NULL,
  `zona` enum('urbana','intermunicipal') NOT NULL DEFAULT 'urbana',
  `status` enum('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(10) UNSIGNED DEFAULT NULL,
  `aprovado_por` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `aprovado_em` datetime DEFAULT NULL
) ;

--
-- Extraindo dados da tabela `pontos_partida`
--

INSERT INTO `pontos_partida` (`id`, `nome`, `cidade`, `lat`, `lng`, `zona`, `status`, `ativo`, `criado_por`, `aprovado_por`, `criado_em`, `aprovado_em`) VALUES
(1, 'Mercado de Sucupira', 'Praia', 14.920424, -23.508929, 'urbana', 'aprovado', 1, NULL, 2, '2026-08-07 13:58:38', '2026-08-09 13:36:14'),
(2, 'Estádio da Várzea', 'Praia', 14.916106, -23.511241, 'urbana', 'aprovado', 1, NULL, 2, '2026-08-07 13:58:38', '2026-08-09 13:12:37'),
(3, 'Assomada', 'Santa Catarina', 15.098300, -23.670300, 'intermunicipal', 'aprovado', 1, NULL, 2, '2026-08-07 13:58:38', '2026-08-09 13:35:50'),
(4, 'Tarrafal', 'Tarrafal', 15.278500, -23.751900, 'intermunicipal', 'aprovado', 1, NULL, 2, '2026-08-07 13:58:38', '2026-08-09 13:12:37'),
(5, 'Cidade Velha', 'Ribeira Grande de Santiago', 14.915300, -23.604700, 'intermunicipal', 'aprovado', 1, NULL, 2, '2026-08-07 13:58:38', '2026-08-09 13:12:37'),
(6, 'Calheta (STG, Cabo Verde)', 'Calheta de São Miguel', 15.188882, -23.593690, 'intermunicipal', 'aprovado', 1, 2, 2, '2026-08-04 19:36:53', '2026-08-09 13:12:37');

-- --------------------------------------------------------

--
-- Estrutura da tabela `precos_km`
--

CREATE TABLE `precos_km` (
  `id` int(10) UNSIGNED NOT NULL,
  `zona` enum('urbana','intermunicipal') NOT NULL,
  `preco_por_km_cve` decimal(10,2) NOT NULL,
  `atualizado_por` int(10) UNSIGNED NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `precos_km`
--

INSERT INTO `precos_km` (`id`, `zona`, `preco_por_km_cve`, `atualizado_por`, `atualizado_em`) VALUES
(1, 'urbana', 5.00, 2, '2026-08-07 15:16:43'),
(2, 'intermunicipal', 10.00, 2, '2026-08-07 15:16:43');

-- --------------------------------------------------------

--
-- Estrutura da tabela `precos_rotas`
--

CREATE TABLE `precos_rotas` (
  `id` int(10) UNSIGNED NOT NULL,
  `ponto_origem_id` int(10) UNSIGNED NOT NULL,
  `ponto_destino_id` int(10) UNSIGNED NOT NULL,
  `preco_fixo_cve` decimal(10,2) NOT NULL,
  `distancia_km` decimal(8,2) DEFAULT NULL,
  `definido_por` int(10) UNSIGNED NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `precos_rotas`
--

INSERT INTO `precos_rotas` (`id`, `ponto_origem_id`, `ponto_destino_id`, `preco_fixo_cve`, `distancia_km`, `definido_por`, `criado_em`) VALUES
(1, 2, 6, 300.00, 31.52, 2, '2026-08-06 21:44:41');

-- --------------------------------------------------------

--
-- Estrutura da tabela `proprietarios`
--

CREATE TABLE `proprietarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `nif` char(9) NOT NULL,
  `utilizador_condutor_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Extraindo dados da tabela `proprietarios`
--

INSERT INTO `proprietarios` (`id`, `nome`, `telefone`, `nif`, `utilizador_condutor_id`, `criado_em`) VALUES
(1, 'Claudina Tavres Lipoes Semedo', '+2389392659', '123456789', NULL, '2026-08-05 16:16:58');

-- --------------------------------------------------------

--
-- Estrutura da tabela `reservas`
--

CREATE TABLE `reservas` (
  `id` int(10) UNSIGNED NOT NULL,
  `passageiro_id` int(10) UNSIGNED NOT NULL,
  `veiculo_id` int(10) UNSIGNED DEFAULT NULL,
  `assento_id` int(10) UNSIGNED DEFAULT NULL,
  `ponto_partida_id` int(10) UNSIGNED NOT NULL,
  `destino_id` int(10) UNSIGNED NOT NULL,
  `ponto_descida_nome` varchar(200) DEFAULT NULL,
  `ponto_descida_lat` decimal(9,6) DEFAULT NULL,
  `ponto_descida_lng` decimal(9,6) DEFAULT NULL,
  `passageiro_lat` decimal(9,6) DEFAULT NULL,
  `passageiro_lng` decimal(9,6) DEFAULT NULL,
  `passageiro_localizacao_em` datetime DEFAULT NULL,
  `lugares` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `motivo` enum('normal','grupo','passeio') NOT NULL DEFAULT 'normal',
  `tipo_viagem` enum('urbano','intermunicipal') NOT NULL DEFAULT 'intermunicipal',
  `preco_final` decimal(10,2) NOT NULL,
  `estado` enum('pendente','confirmado','recusado','a_bordo','concluido') NOT NULL DEFAULT 'pendente',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `reservas`
--

INSERT INTO `reservas` (`id`, `passageiro_id`, `veiculo_id`, `assento_id`, `ponto_partida_id`, `destino_id`, `ponto_descida_nome`, `ponto_descida_lat`, `ponto_descida_lng`, `passageiro_lat`, `passageiro_lng`, `passageiro_localizacao_em`, `lugares`, `motivo`, `tipo_viagem`, `preco_final`, `estado`, `criado_em`, `atualizado_em`) VALUES
(4, 11, 7, 71, 1, 6, NULL, NULL, NULL, 14.910553, -23.519284, '2026-08-10 11:28:58', 1, 'normal', 'intermunicipal', 293.99, 'recusado', '2026-08-09 00:33:50', '2026-08-10 22:01:40'),
(5, 13, 2, 17, 1, 6, 'Monte Bode, São Miguel, 7215, Cabo Verde', 15.171283, -23.636319, 14.910645, -23.518698, '2026-08-09 18:37:29', 1, 'normal', 'intermunicipal', 219.84, 'concluido', '2026-08-09 15:02:28', '2026-08-09 23:45:20'),
(7, 14, NULL, NULL, 2, 2, 'Achada São Filipe, Praia, Agostinho Alvies, Praia, Cabo Verde', 14.953807, -23.517652, 14.910648, -23.518676, '2026-08-09 19:58:18', 1, 'normal', 'urbano', 100.00, 'recusado', '2026-08-09 19:49:24', '2026-08-09 19:58:22'),
(8, 14, 8, 85, 2, 2, 'Ribeira de São Filipe, Praia, Agostinho Alvies, Praia, Cabo Verde', 14.956233, -23.510008, 14.910645, -23.518698, '2026-08-09 20:05:27', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 20:01:17', '2026-08-09 20:05:32'),
(10, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910645, -23.518698, '2026-08-09 20:29:03', 1, 'normal', 'urbano', 100.00, 'recusado', '2026-08-09 20:26:21', '2026-08-09 20:29:09'),
(11, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910648, -23.518676, '2026-08-09 20:33:49', 1, 'normal', 'urbano', 100.00, 'recusado', '2026-08-09 20:29:32', '2026-08-09 20:33:54'),
(13, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910648, -23.518676, '2026-08-09 21:43:19', 1, 'normal', 'urbano', 100.00, 'recusado', '2026-08-09 21:02:51', '2026-08-09 21:43:20'),
(15, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910645, -23.518698, '2026-08-09 21:46:44', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 21:43:47', '2026-08-09 21:46:55'),
(16, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910646, -23.518686, '2026-08-09 21:58:57', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 21:48:42', '2026-08-09 21:59:03'),
(17, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910645, -23.518698, '2026-08-09 22:25:25', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 22:17:47', '2026-08-09 22:25:27'),
(19, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910648, -23.518676, '2026-08-09 23:08:57', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 23:08:16', '2026-08-09 23:09:02'),
(20, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910648, -23.518676, '2026-08-09 23:18:29', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 23:09:49', '2026-08-09 23:18:29'),
(21, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910648, -23.518676, '2026-08-09 23:20:47', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 23:20:13', '2026-08-09 23:20:50'),
(22, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.910645, -23.518698, '2026-08-09 23:42:29', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-09 23:21:09', '2026-08-09 23:43:04'),
(24, 14, 8, 85, 2, 2, 'Palmarejo, Praia, 5298, Cabo Verde', 14.912684, -23.526747, 14.900901, -23.530005, '2026-08-10 19:07:57', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-10 00:01:32', '2026-08-10 19:07:57'),
(29, 14, NULL, NULL, 2, 2, 'Praia Senhora de Encarnação, Alto de Santa Luzia, Nossa Senhora da Conceição, São Filipe, 8220, Cabo Verde', 14.878261, -24.487872, 14.900901, -23.530005, '2026-08-10 19:10:15', 1, 'normal', 'urbano', 673.89, 'recusado', '2026-08-10 19:09:34', '2026-08-10 19:10:20'),
(30, 14, 8, 85, 2, 2, 'São Filipe de Baixo, Praia, Agostinho Alvies, Praia, 7601, Cabo Verde', 15.046739, -23.524370, 14.900901, -23.530005, '2026-08-10 19:51:48', 1, 'normal', 'urbano', 100.00, 'recusado', '2026-08-10 19:10:59', '2026-08-10 20:36:11'),
(31, 14, NULL, NULL, 2, 2, 'São Miguel, Assomada-Calheta, Lem Gomes, Calheta de São Miguel, São Miguel, 7215, Cabo Verde', 15.180364, -23.637740, 14.900901, -23.530005, '2026-08-10 20:41:52', 1, 'normal', 'urbano', 276.07, 'recusado', '2026-08-10 20:41:52', '2026-08-10 20:42:11'),
(32, 14, 8, 85, 2, 2, 'São Filipe de Baixo, Praia, Agostinho Alvies, Praia, 7601, Cabo Verde', 14.954089, -23.514624, 14.910234, -23.519545, '2026-08-10 21:12:12', 1, 'normal', 'urbano', 100.00, 'concluido', '2026-08-10 20:52:49', '2026-08-10 21:43:22'),
(33, 14, 6, 57, 2, 6, NULL, NULL, NULL, 14.910216, -23.519524, '2026-08-10 22:00:12', 1, 'normal', 'intermunicipal', 223.12, 'recusado', '2026-08-10 21:56:59', '2026-08-11 09:19:28'),
(35, 11, 6, 58, 2, 6, 'Monte Bode, São Miguel, 7215, Cabo Verde', 15.171283, -23.636319, NULL, NULL, NULL, 1, 'normal', 'intermunicipal', 221.68, 'recusado', '2026-08-10 22:04:13', '2026-08-10 23:48:42'),
(37, 14, 18, 130, 2, 2, 'Plateau Banco Interatlantico, Avenida Amilcar Cabral, Platô, Praia, Platô, Praia, 7600, Cabo Verde', 14.918170, -23.509302, 14.918919, -23.531975, '2026-08-11 09:20:01', 1, 'normal', 'urbano', 100.00, 'confirmado', '2026-08-11 09:20:01', '2026-08-11 09:53:03');

-- --------------------------------------------------------

--
-- Estrutura da tabela `sinalizacao_chamada`
--

CREATE TABLE `sinalizacao_chamada` (
  `id` int(10) UNSIGNED NOT NULL,
  `chamada_id` int(10) UNSIGNED NOT NULL,
  `remetente_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('offer','answer','ice') NOT NULL,
  `payload` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `sinalizacao_chamada`
--

INSERT INTO `sinalizacao_chamada` (`id`, `chamada_id`, `remetente_id`, `tipo`, `payload`, `criado_em`) VALUES
(4, 11, 14, 'offer', '{\"sdp\":\"v=0\\r\\no=- 1912256596278107863 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 66503619-feb1-41f7-8d00-2b03e058e281\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:FLEG\\r\\na=ice-pwd:lRohwxttcp+uwN5gLO57I60i\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 E5:BF:2A:4F:46:33:67:6A:9E:16:2B:F0:C3:D7:2B:91:41:81:1D:CB:AA:06:35:96:DA:10:02:7B:63:81:FD:E0\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:66503619-feb1-41f7-8d00-2b03e058e281 022b1989-dda4-4444-a43b-c50d9370d52d\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:1039322859 cname:wHu3/xj3iu8MC8kx\\r\\na=ssrc:1039322859 msid:66503619-feb1-41f7-8d00-2b03e058e281 022b1989-dda4-4444-a43b-c50d9370d52d\\r\\n\",\"type\":\"offer\"}', '2026-08-10 19:02:10'),
(5, 11, 14, 'ice', '{\"candidate\":\"candidate:960469628 1 udp 1686052607 165.90.96.233 34670 typ srflx raddr 192.168.1.146 rport 34670 generation 0 ufrag FLEG network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"FLEG\"}', '2026-08-10 19:02:12'),
(6, 11, 14, 'ice', '{\"candidate\":\"candidate:4176596278 1 udp 2122260223 192.168.1.146 34670 typ host generation 0 ufrag FLEG network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"FLEG\"}', '2026-08-10 19:02:13'),
(7, 11, 14, 'ice', '{\"candidate\":\"candidate:106633634 1 tcp 1518280447 192.168.1.146 9 typ host tcptype active generation 0 ufrag FLEG network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"FLEG\"}', '2026-08-10 19:02:14'),
(8, 11, 16, 'answer', '{\"sdp\":\"v=0\\r\\no=- 582135238333261034 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 35f5490b-a4be-4a91-8c9f-7cb3e0202398\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:qlmu\\r\\na=ice-pwd:GioLlDgsgmnpi3NjOI3x4Q5V\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 EE:E2:1B:1E:72:01:B1:47:5D:C0:71:0D:E8:9A:EB:5D:C3:81:D7:27:7E:72:82:5E:EB:BD:B8:CB:34:B0:24:E2\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:35f5490b-a4be-4a91-8c9f-7cb3e0202398 bf872233-4ec8-4ff0-a253-1c68101d873f\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2296422746 cname:iPEh6WevxxoYzKmq\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:02:37'),
(9, 11, 16, 'ice', '{\"candidate\":\"candidate:1991000662 1 udp 2122260223 192.168.1.191 61623 typ host generation 0 ufrag qlmu network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"qlmu\"}', '2026-08-10 19:02:37'),
(10, 11, 16, 'ice', '{\"candidate\":\"candidate:140746958 1 tcp 1518280447 192.168.1.191 9 typ host tcptype active generation 0 ufrag qlmu network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"qlmu\"}', '2026-08-10 19:02:38'),
(11, 11, 16, 'answer', '{\"sdp\":\"v=0\\r\\no=- 582135238333261034 3 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 35f5490b-a4be-4a91-8c9f-7cb3e0202398\\r\\nm=audio 61623 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 192.168.1.191\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=candidate:1991000662 1 udp 2122260223 192.168.1.191 61623 typ host generation 0 network-id 1 network-cost 10\\r\\na=ice-ufrag:qlmu\\r\\na=ice-pwd:GioLlDgsgmnpi3NjOI3x4Q5V\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 EE:E2:1B:1E:72:01:B1:47:5D:C0:71:0D:E8:9A:EB:5D:C3:81:D7:27:7E:72:82:5E:EB:BD:B8:CB:34:B0:24:E2\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:35f5490b-a4be-4a91-8c9f-7cb3e0202398 bf872233-4ec8-4ff0-a253-1c68101d873f\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2296422746 cname:iPEh6WevxxoYzKmq\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:02:38'),
(12, 11, 16, 'answer', '{\"sdp\":\"v=0\\r\\no=- 582135238333261034 4 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 35f5490b-a4be-4a91-8c9f-7cb3e0202398\\r\\nm=audio 61623 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 192.168.1.191\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=candidate:1991000662 1 udp 2122260223 192.168.1.191 61623 typ host generation 0 network-id 1 network-cost 10\\r\\na=candidate:140746958 1 tcp 1518280447 192.168.1.191 9 typ host tcptype active generation 0 network-id 1 network-cost 10\\r\\na=ice-ufrag:qlmu\\r\\na=ice-pwd:GioLlDgsgmnpi3NjOI3x4Q5V\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 EE:E2:1B:1E:72:01:B1:47:5D:C0:71:0D:E8:9A:EB:5D:C3:81:D7:27:7E:72:82:5E:EB:BD:B8:CB:34:B0:24:E2\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:35f5490b-a4be-4a91-8c9f-7cb3e0202398 bf872233-4ec8-4ff0-a253-1c68101d873f\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2296422746 cname:iPEh6WevxxoYzKmq\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:02:42'),
(13, 11, 16, 'answer', '{\"sdp\":\"v=0\\r\\no=- 582135238333261034 5 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 35f5490b-a4be-4a91-8c9f-7cb3e0202398\\r\\nm=audio 61623 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 192.168.1.191\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=candidate:1991000662 1 udp 2122260223 192.168.1.191 61623 typ host generation 0 network-id 1 network-cost 10\\r\\na=candidate:140746958 1 tcp 1518280447 192.168.1.191 9 typ host tcptype active generation 0 network-id 1 network-cost 10\\r\\na=ice-ufrag:qlmu\\r\\na=ice-pwd:GioLlDgsgmnpi3NjOI3x4Q5V\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 EE:E2:1B:1E:72:01:B1:47:5D:C0:71:0D:E8:9A:EB:5D:C3:81:D7:27:7E:72:82:5E:EB:BD:B8:CB:34:B0:24:E2\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:35f5490b-a4be-4a91-8c9f-7cb3e0202398 bf872233-4ec8-4ff0-a253-1c68101d873f\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2296422746 cname:iPEh6WevxxoYzKmq\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:02:42'),
(14, 12, 16, 'offer', '{\"sdp\":\"v=0\\r\\no=- 6655573968514357912 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 21856ab3-31ff-428c-b403-b8cc814e5a3e\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:L6J6\\r\\na=ice-pwd:TbFu5aOI2FvaqRIDTDSUCqeA\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 8E:FB:D9:A7:44:F7:81:89:1C:53:AF:52:C5:8C:C1:46:30:B4:8D:D8:F1:C6:EF:65:09:27:F6:C9:F1:FF:D0:32\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:21856ab3-31ff-428c-b403-b8cc814e5a3e 36967e55-7aa4-4a98-8d24-e11a326f01c4\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2549317118 cname:DkBE+Oj38EIuBjB7\\r\\na=ssrc:2549317118 msid:21856ab3-31ff-428c-b403-b8cc814e5a3e 36967e55-7aa4-4a98-8d24-e11a326f01c4\\r\\n\",\"type\":\"offer\"}', '2026-08-10 19:43:18'),
(15, 12, 16, 'ice', '{\"candidate\":\"candidate:3790861007 1 tcp 1518280447 192.168.1.220 9 typ host tcptype active generation 0 ufrag L6J6 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"L6J6\"}', '2026-08-10 19:43:18'),
(16, 12, 16, 'ice', '{\"candidate\":\"candidate:2671513687 1 udp 2122260223 192.168.1.220 41847 typ host generation 0 ufrag L6J6 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"L6J6\"}', '2026-08-10 19:43:18'),
(17, 12, 16, 'ice', '{\"candidate\":\"candidate:813101417 1 udp 1686052607 165.90.96.233 41847 typ srflx raddr 192.168.1.220 rport 41847 generation 0 ufrag L6J6 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"L6J6\"}', '2026-08-10 19:43:18'),
(18, 12, 14, 'answer', '{\"sdp\":\"v=0\\r\\no=- 8623723762349157345 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 8f079366-d1f2-4035-a6c7-e51a08bbaebc\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:O749\\r\\na=ice-pwd:OHpaufAGiCx3HyRjXPnuTK7X\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 AD:69:4B:73:CB:B6:4D:A9:CF:E8:03:D8:BC:BD:EB:00:BF:DA:22:ED:F8:3D:ED:D0:40:90:1C:E3:F7:81:52:4C\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:8f079366-d1f2-4035-a6c7-e51a08bbaebc cfbe6c3a-1815-4874-a412-2697d32a11e9\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:3747879438 cname:N0AJkdjjJQLdVMqF\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:43:26'),
(19, 12, 14, 'ice', '{\"candidate\":\"candidate:2993721152 1 udp 2122260223 192.168.1.146 36604 typ host generation 0 ufrag O749 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"O749\"}', '2026-08-10 19:43:27'),
(20, 12, 14, 'ice', '{\"candidate\":\"candidate:3435097560 1 tcp 1518280447 192.168.1.146 9 typ host tcptype active generation 0 ufrag O749 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"O749\"}', '2026-08-10 19:43:27'),
(21, 13, 14, 'offer', '{\"sdp\":\"v=0\\r\\no=- 1771201230865325878 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 77f1b423-9411-4ed0-844c-8d22585b2194\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:6zSE\\r\\na=ice-pwd:Tgu5rBssDVLXLJFJBXHJmpCl\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 A2:25:57:A5:77:BA:77:14:81:75:68:53:D3:D7:B4:0F:1C:94:DC:0E:C6:D7:72:50:F1:F5:C0:B2:E7:10:64:4D\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:77f1b423-9411-4ed0-844c-8d22585b2194 5b4fef12-dec5-41f1-9ae8-57019fff3876\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:3670435086 cname:6mu+BIr7Tb0ilnuV\\r\\na=ssrc:3670435086 msid:77f1b423-9411-4ed0-844c-8d22585b2194 5b4fef12-dec5-41f1-9ae8-57019fff3876\\r\\n\",\"type\":\"offer\"}', '2026-08-10 19:45:49'),
(22, 13, 14, 'ice', '{\"candidate\":\"candidate:179878682 1 udp 1686052607 165.90.96.233 37988 typ srflx raddr 192.168.1.146 rport 37988 generation 0 ufrag 6zSE network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"6zSE\"}', '2026-08-10 19:45:49'),
(23, 13, 14, 'ice', '{\"candidate\":\"candidate:2784123428 1 udp 2122260223 192.168.1.146 37988 typ host generation 0 ufrag 6zSE network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"6zSE\"}', '2026-08-10 19:45:49'),
(24, 13, 14, 'ice', '{\"candidate\":\"candidate:3678255292 1 tcp 1518280447 192.168.1.146 9 typ host tcptype active generation 0 ufrag 6zSE network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"6zSE\"}', '2026-08-10 19:45:49'),
(25, 14, 16, 'offer', '{\"sdp\":\"v=0\\r\\no=- 2427308845589600494 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS bc61a472-d375-4ba6-a8e5-6060c08718ad\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:/G18\\r\\na=ice-pwd:IjuFFwNMQFk0c7ltOvS7e2L4\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 62:C4:08:14:2D:33:01:C2:6D:A8:18:00:95:A6:3A:26:99:5B:74:13:56:F8:8C:62:B4:8A:40:21:58:34:19:53\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:bc61a472-d375-4ba6-a8e5-6060c08718ad 356cb10d-9cb8-4110-8763-89a3b7df8fa5\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:3545246539 cname:R1ClMM3i+6gUPEDy\\r\\na=ssrc:3545246539 msid:bc61a472-d375-4ba6-a8e5-6060c08718ad 356cb10d-9cb8-4110-8763-89a3b7df8fa5\\r\\n\",\"type\":\"offer\"}', '2026-08-10 19:48:57'),
(26, 14, 16, 'ice', '{\"candidate\":\"candidate:3493034871 1 udp 2122260223 192.168.1.220 36326 typ host generation 0 ufrag /G18 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"/G18\"}', '2026-08-10 19:48:57'),
(27, 14, 16, 'ice', '{\"candidate\":\"candidate:2138675785 1 udp 1686052607 165.90.96.233 36326 typ srflx raddr 192.168.1.220 rport 36326 generation 0 ufrag /G18 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"/G18\"}', '2026-08-10 19:48:57'),
(28, 14, 16, 'ice', '{\"candidate\":\"candidate:2935790063 1 tcp 1518280447 192.168.1.220 9 typ host tcptype active generation 0 ufrag /G18 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"/G18\"}', '2026-08-10 19:48:57'),
(29, 14, 14, 'answer', '{\"sdp\":\"v=0\\r\\no=- 6986140928445258316 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 5cf7e4bb-09fe-415b-9460-8fd3be2bf9c3\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:LkSq\\r\\na=ice-pwd:nzBvlYqVSQg1fP+8uU5hHuZ6\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 B7:BF:95:0F:96:D0:02:25:84:CF:F9:24:00:A3:04:F3:8F:BD:5D:AB:7D:3C:36:EA:07:27:0D:9D:35:E7:B7:2A\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:5cf7e4bb-09fe-415b-9460-8fd3be2bf9c3 c1796e3d-d507-469b-9cc9-f92119ec5338\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:355172391 cname:3KZ1Y8BvpepY84bz\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:49:02'),
(30, 14, 14, 'ice', '{\"candidate\":\"candidate:3676165462 1 udp 2122260223 192.168.1.146 37383 typ host generation 0 ufrag LkSq network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"LkSq\"}', '2026-08-10 19:49:03'),
(31, 14, 14, 'ice', '{\"candidate\":\"candidate:450069020 1 udp 1686052607 165.90.96.233 37383 typ srflx raddr 192.168.1.146 rport 37383 generation 0 ufrag LkSq network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"LkSq\"}', '2026-08-10 19:49:03'),
(32, 14, 14, 'ice', '{\"candidate\":\"candidate:632750530 1 tcp 1518280447 192.168.1.146 9 typ host tcptype active generation 0 ufrag LkSq network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"LkSq\"}', '2026-08-10 19:49:03'),
(33, 15, 14, 'ice', '{\"candidate\":\"candidate:4239280431 1 udp 2122260223 192.168.1.146 59575 typ host generation 0 ufrag eork network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"eork\"}', '2026-08-10 19:49:31'),
(34, 15, 14, 'offer', '{\"sdp\":\"v=0\\r\\no=- 5577181480032580931 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 92228182-26f7-460d-abbd-6a7db128873c\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:eork\\r\\na=ice-pwd:OpCq1TFNDMmo58cV+Kd56H5y\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 7F:59:B8:A5:C1:41:2D:02:DB:A7:0A:75:D6:09:A6:E0:06:2F:B9:47:00:1E:36:9F:D9:34:33:48:C2:BA:A3:B8\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:92228182-26f7-460d-abbd-6a7db128873c e40c800c-676d-4789-8b6d-c647cc16a41a\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:3119244998 cname:tgrufZNmnkn7H6W2\\r\\na=ssrc:3119244998 msid:92228182-26f7-460d-abbd-6a7db128873c e40c800c-676d-4789-8b6d-c647cc16a41a\\r\\n\",\"type\":\"offer\"}', '2026-08-10 19:49:31'),
(35, 15, 14, 'ice', '{\"candidate\":\"candidate:1407494161 1 udp 1686052607 165.90.96.233 59575 typ srflx raddr 192.168.1.146 rport 59575 generation 0 ufrag eork network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"eork\"}', '2026-08-10 19:49:31'),
(36, 15, 14, 'ice', '{\"candidate\":\"candidate:2187439031 1 tcp 1518280447 192.168.1.146 9 typ host tcptype active generation 0 ufrag eork network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"eork\"}', '2026-08-10 19:49:32'),
(37, 15, 16, 'answer', '{\"sdp\":\"v=0\\r\\no=- 8320263589706801760 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS e5444ca6-dac5-4837-9253-b5d8e5b4c48f\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:Fhf7\\r\\na=ice-pwd:j4piyQ2hmcAKG0swmN3LfaYw\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 7A:48:F3:1A:7C:F8:2F:D3:E6:A3:A2:8D:F6:61:DC:B3:A1:76:2E:C3:92:E5:4C:61:DF:56:C6:A9:7A:17:D2:29\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:e5444ca6-dac5-4837-9253-b5d8e5b4c48f bf3fd4fb-54e5-4cb2-9df9-c5302cab5546\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:3196203725 cname:UsH+1NBaJTdCmI0C\\r\\n\",\"type\":\"answer\"}', '2026-08-10 19:49:37'),
(38, 15, 16, 'ice', '{\"candidate\":\"candidate:1018939685 1 udp 2122260223 192.168.1.220 56375 typ host generation 0 ufrag Fhf7 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Fhf7\"}', '2026-08-10 19:49:37'),
(39, 15, 16, 'ice', '{\"candidate\":\"candidate:4252343919 1 udp 1686052607 165.90.96.233 56375 typ srflx raddr 192.168.1.220 rport 56375 generation 0 ufrag Fhf7 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Fhf7\"}', '2026-08-10 19:49:37'),
(40, 15, 16, 'ice', '{\"candidate\":\"candidate:3255901617 1 tcp 1518280447 192.168.1.220 9 typ host tcptype active generation 0 ufrag Fhf7 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Fhf7\"}', '2026-08-10 19:49:37'),
(41, 16, 14, 'offer', '{\"sdp\":\"v=0\\r\\no=- 605563892661583947 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS a11c9d2b-2465-4722-8fd7-db0476b6c3d9\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:iokZ\\r\\na=ice-pwd:l1kFJsaonaNPBqAslYS+avEs\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 2A:BB:84:0B:A8:0E:A4:52:11:8B:0B:3A:1D:EA:47:EC:20:5B:DB:59:35:29:92:CB:94:AE:6D:DC:E7:AA:D4:8B\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:a11c9d2b-2465-4722-8fd7-db0476b6c3d9 65dbc02b-759d-4420-8c0f-ba6b2f0cabd6\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:1463704537 cname:sXj7BVDUXvPxE++n\\r\\na=ssrc:1463704537 msid:a11c9d2b-2465-4722-8fd7-db0476b6c3d9 65dbc02b-759d-4420-8c0f-ba6b2f0cabd6\\r\\n\",\"type\":\"offer\"}', '2026-08-10 20:30:27'),
(42, 16, 14, 'ice', '{\"candidate\":\"candidate:84640397 1 tcp 1518280447 192.168.1.146 9 typ host tcptype active generation 0 ufrag iokZ network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"iokZ\"}', '2026-08-10 20:30:28'),
(43, 16, 14, 'ice', '{\"candidate\":\"candidate:2076467221 1 udp 2122260223 192.168.1.146 38857 typ host generation 0 ufrag iokZ network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"iokZ\"}', '2026-08-10 20:30:28'),
(44, 16, 14, 'ice', '{\"candidate\":\"candidate:3566108971 1 udp 1686052607 165.90.96.233 38857 typ srflx raddr 192.168.1.146 rport 38857 generation 0 ufrag iokZ network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"iokZ\"}', '2026-08-10 20:30:28'),
(45, 16, 16, 'answer', '{\"sdp\":\"v=0\\r\\no=- 3023640665419620498 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 7e79467c-19ad-45ab-8223-e8a726a57dbb\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:Mnw6\\r\\na=ice-pwd:s+wfn5ql46xcdlA9sp1Rx4PR\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 90:A0:C2:68:71:A6:CB:CA:4C:42:D6:10:77:77:0B:3D:DB:70:42:A6:9F:D4:0A:B7:28:71:1A:B3:F2:AE:7B:D6\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:7e79467c-19ad-45ab-8223-e8a726a57dbb 34c75ca5-0abd-464d-b16d-441f039da11f\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:1044633653 cname:KHzDzTrDjWKyJO/v\\r\\n\",\"type\":\"answer\"}', '2026-08-10 20:31:17'),
(46, 16, 16, 'ice', '{\"candidate\":\"candidate:1077863198 1 udp 2122260223 192.168.1.220 33564 typ host generation 0 ufrag Mnw6 network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Mnw6\"}', '2026-08-10 20:31:18'),
(47, 17, 16, 'offer', '{\"sdp\":\"v=0\\r\\no=- 539636429286793814 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS ac00ea63-68e7-472d-ad09-b17fc7c8ccb6\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:Zs0z\\r\\na=ice-pwd:198sFmhVYzs1rWy2NpJDRoMR\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 F6:C9:FD:5E:0E:16:2F:E4:4B:6B:AF:EF:09:E3:D9:CE:32:22:D3:45:37:71:8F:47:5C:A5:B4:70:07:ED:EA:5D\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:ac00ea63-68e7-472d-ad09-b17fc7c8ccb6 a5ca4b2f-53ad-45ed-9d56-864a4d28728a\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2410766881 cname:fCsvOH8IM92+M5gT\\r\\na=ssrc:2410766881 msid:ac00ea63-68e7-472d-ad09-b17fc7c8ccb6 a5ca4b2f-53ad-45ed-9d56-864a4d28728a\\r\\n\",\"type\":\"offer\"}', '2026-08-10 20:31:47'),
(48, 17, 16, 'ice', '{\"candidate\":\"candidate:2793078910 1 udp 2122260223 192.168.1.220 34449 typ host generation 0 ufrag Zs0z network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Zs0z\"}', '2026-08-10 20:31:47'),
(49, 17, 16, 'ice', '{\"candidate\":\"candidate:3635733222 1 tcp 1518280447 192.168.1.220 9 typ host tcptype active generation 0 ufrag Zs0z network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Zs0z\"}', '2026-08-10 20:31:47'),
(50, 17, 16, 'ice', '{\"candidate\":\"candidate:154263872 1 udp 1686052607 165.90.96.233 34449 typ srflx raddr 192.168.1.220 rport 34449 generation 0 ufrag Zs0z network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"Zs0z\"}', '2026-08-10 20:31:47'),
(51, 17, 14, 'answer', '{\"sdp\":\"v=0\\r\\no=- 9135901635773923233 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS 1a016517-4718-450b-89a8-fc98a5f0455e\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:lDgz\\r\\na=ice-pwd:w1mcuiDOLGpu6BhtzLiT/w1c\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 C9:6C:3E:F4:38:3E:FF:66:40:3A:FF:D9:DE:48:2B:6F:15:28:EA:8F:BF:35:CE:78:67:D2:BA:A7:E6:75:D6:81\\r\\na=setup:active\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:1a016517-4718-450b-89a8-fc98a5f0455e 49d8f0b7-59c5-49c1-9aa2-785f6119dd79\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:1950429078 cname:WfsQHUI9E2xQwBu5\\r\\n\",\"type\":\"answer\"}', '2026-08-10 20:32:04'),
(52, 17, 14, 'ice', '{\"candidate\":\"candidate:545603062 1 udp 2122260223 192.168.1.146 53058 typ host generation 0 ufrag lDgz network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"lDgz\"}', '2026-08-10 20:32:04'),
(53, 18, 14, 'offer', '{\"sdp\":\"v=0\\r\\no=- 6001871625269570674 2 IN IP4 127.0.0.1\\r\\ns=-\\r\\nt=0 0\\r\\na=group:BUNDLE 0\\r\\na=extmap-allow-mixed\\r\\na=msid-semantic: WMS ce64d546-04ed-445e-ae7d-54f6cc34ac5a\\r\\nm=audio 9 UDP/TLS/RTP/SAVPF 111 63 9 0 8 13 110 126\\r\\nc=IN IP4 0.0.0.0\\r\\na=rtcp:9 IN IP4 0.0.0.0\\r\\na=ice-ufrag:U7Jf\\r\\na=ice-pwd:Jh5Erm3lS9GIB/+NdglofIFc\\r\\na=ice-options:trickle\\r\\na=fingerprint:sha-256 CF:95:12:ED:B1:51:A7:BA:EC:79:EF:B3:50:74:46:52:C5:70:B8:A5:66:21:76:23:4F:11:16:62:6B:F9:D0:33\\r\\na=setup:actpass\\r\\na=mid:0\\r\\na=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level\\r\\na=extmap:2 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time\\r\\na=extmap:3 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01\\r\\na=extmap:4 urn:ietf:params:rtp-hdrext:sdes:mid\\r\\na=sendrecv\\r\\na=msid:ce64d546-04ed-445e-ae7d-54f6cc34ac5a 9a66e44b-f646-4386-96d3-b11c3e57a147\\r\\na=rtcp-mux\\r\\na=rtcp-rsize\\r\\na=rtpmap:111 opus/48000/2\\r\\na=rtcp-fb:111 transport-cc\\r\\na=fmtp:111 minptime=10;useinbandfec=1\\r\\na=rtpmap:63 red/48000/2\\r\\na=fmtp:63 111/111\\r\\na=rtpmap:9 G722/8000\\r\\na=rtpmap:0 PCMU/8000\\r\\na=rtpmap:8 PCMA/8000\\r\\na=rtpmap:13 CN/8000\\r\\na=rtpmap:110 telephone-event/48000\\r\\na=rtpmap:126 telephone-event/8000\\r\\na=ssrc:2592978968 cname:4n4jkdwqVhwaDnhV\\r\\na=ssrc:2592978968 msid:ce64d546-04ed-445e-ae7d-54f6cc34ac5a 9a66e44b-f646-4386-96d3-b11c3e57a147\\r\\n\",\"type\":\"offer\"}', '2026-08-10 21:01:29'),
(54, 18, 14, 'ice', '{\"candidate\":\"candidate:918872547 1 tcp 1518280447 192.168.1.220 9 typ host tcptype active generation 0 ufrag U7Jf network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"U7Jf\"}', '2026-08-10 21:01:29'),
(55, 18, 14, 'ice', '{\"candidate\":\"candidate:1213908206 1 udp 2122260223 192.168.1.220 39615 typ host generation 0 ufrag U7Jf network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"U7Jf\"}', '2026-08-10 21:01:30'),
(56, 18, 14, 'ice', '{\"candidate\":\"candidate:144750151 1 udp 1686052607 165.90.96.233 39615 typ srflx raddr 192.168.1.220 rport 39615 generation 0 ufrag U7Jf network-id 1 network-cost 10\",\"sdpMid\":\"0\",\"sdpMLineIndex\":0,\"usernameFragment\":\"U7Jf\"}', '2026-08-10 21:01:30');

-- --------------------------------------------------------

--
-- Estrutura da tabela `sugestoes`
--

CREATE TABLE `sugestoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilizador_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('sugestao','reclamacao') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text NOT NULL,
  `condutor_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pendente','visto','implementado','resolvido') NOT NULL DEFAULT 'pendente',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `sugestoes`
--

INSERT INTO `sugestoes` (`id`, `utilizador_id`, `tipo`, `titulo`, `descricao`, `condutor_id`, `status`, `criado_em`, `atualizado_em`) VALUES
(3, 11, 'sugestao', 'ponto', 'nao consigo ver a minha localizaçao nem do carro no ponto nem a fila', NULL, 'pendente', '2026-08-09 08:54:45', '2026-08-09 08:54:45');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('passageiro','condutor') NOT NULL,
  `nome` varchar(150) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `nif` char(9) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `status` enum('ativo','pendente','suspenso') NOT NULL DEFAULT 'ativo',
  `proprietario_id` int(10) UNSIGNED DEFAULT NULL,
  `tentativas_login` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id`, `tipo`, `nome`, `telefone`, `nif`, `email`, `senha_hash`, `status`, `proprietario_id`, `tentativas_login`, `bloqueado_ate`, `criado_em`, `atualizado_em`) VALUES
(3, 'condutor', 'PR', '+2389392659', '123345777', 'victoralison@gmail.com', '$2y$10$svCGFRIIqj3ZT5Zy1jb5i.A92oZOeQEa1L8jdzMASzvoRI.3gVsNG', 'ativo', 1, 0, NULL, '2026-08-07 09:30:19', '2026-08-07 14:43:23'),
(4, 'passageiro', 'TL', '+2385947134', '123456789', 'semedoalex74@gmail.com', '$2y$10$DP8IfoLVmul8aLkaZbeqVOGglN/g1VLtzNo706CmX4SburYfUCsF2', 'ativo', NULL, 0, NULL, '2026-08-07 11:49:02', '2026-08-07 14:43:23'),
(11, 'passageiro', 'Valdir Tavares', '+2389392652', '123456788', NULL, '$2y$10$v1ICvcHyBbgvJdJGX3BM7.5MKr1r8lDH6rDrABmNokxpe0K8uvJIu', 'ativo', NULL, 0, NULL, '2026-08-08 23:03:40', '2026-08-08 23:03:40'),
(12, 'condutor', 'Claudina Semedo', '+2389898815', '122133433', NULL, '$2y$10$os9ELFUohN7gR6U/OjvRaOfU0rtuT.bo3SQBDIC1HFMXUTNlvwM.O', 'ativo', NULL, 0, NULL, '2026-08-08 23:08:17', '2026-08-08 23:09:43'),
(13, 'passageiro', 'Hellen Semedo', '+2389392651', '112321122', NULL, '$2y$10$RcUbiu1s5o8lffonsBsq9OD7fkWACwbEqkSyrved4oz2ZO6vw2icG', 'ativo', NULL, 0, NULL, '2026-08-09 14:56:17', '2026-08-09 14:56:17'),
(14, 'passageiro', 'Bela', '+2389382659', '122113456', NULL, '$2y$10$a/LgFwakpoEAV5tefQMrGeVp.TAEGLy2ACBSWSvhbVx1hFApp.tO6', 'ativo', NULL, 0, NULL, '2026-08-09 16:13:29', '2026-08-09 16:13:29'),
(16, 'condutor', 'Blaika Semedo', '+2385947131', '112222335', NULL, '$2y$10$npa6TJeiPetl3rCzcnPZ3OMnovq5T.JoYnCOsxw4.7C1RiLo4r2WO', 'ativo', NULL, 0, NULL, '2026-08-09 19:52:05', '2026-08-09 19:54:52');

-- --------------------------------------------------------

--
-- Estrutura da tabela `veiculos`
--

CREATE TABLE `veiculos` (
  `id` int(10) UNSIGNED NOT NULL,
  `condutor_id` int(10) UNSIGNED NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `tipo` enum('hiace','taxi','autocarro') NOT NULL,
  `tipo_servico` enum('urbano','intermunicipal','ambos') NOT NULL DEFAULT 'ambos',
  `cor` varchar(40) NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `lugares_total` tinyint(3) UNSIGNED NOT NULL DEFAULT 14,
  `lugares_livres` tinyint(3) UNSIGNED NOT NULL DEFAULT 14,
  `estado` enum('no_ponto','na_fila','em_movimento','partiu_da_fila','chegou_destino') NOT NULL DEFAULT 'no_ponto',
  `aprovado` tinyint(1) NOT NULL DEFAULT 0,
  `ponto_partida_id` int(10) UNSIGNED DEFAULT NULL,
  `destino_id` int(10) UNSIGNED DEFAULT NULL,
  `rota_fixa_id` int(10) UNSIGNED DEFAULT NULL,
  `posicao_fila` smallint(5) UNSIGNED DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `ultima_posicao_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Extraindo dados da tabela `veiculos`
--

INSERT INTO `veiculos` (`id`, `condutor_id`, `matricula`, `tipo`, `tipo_servico`, `cor`, `modelo`, `lugares_total`, `lugares_livres`, `estado`, `aprovado`, `ponto_partida_id`, `destino_id`, `rota_fixa_id`, `posicao_fila`, `lat`, `lng`, `ultima_posicao_em`, `criado_em`) VALUES
(2, 3, 'ST - 01 - GG', 'hiace', 'ambos', 'Branco', 'Normal', 14, 14, 'no_ponto', 0, 1, 5, NULL, NULL, 14.920424, -23.508929, '2026-08-10 20:15:36', '2026-08-07 09:34:21'),
(6, 12, 'ST - 00 - GG', 'hiace', 'ambos', 'Branco', 'Turbo', 14, 14, 'no_ponto', 1, 2, 6, NULL, NULL, 14.916106, -23.511241, '2026-08-10 21:55:58', '2026-08-08 23:11:58'),
(7, 12, 'ST - 09 - GG', 'hiace', 'intermunicipal', 'verde', 'Turbo', 14, 14, 'no_ponto', 1, 1, 6, 1, NULL, 14.920424, -23.508929, '2026-08-09 15:43:32', '2026-08-09 00:29:16'),
(8, 16, 'ST - 88 - YG', 'taxi', 'urbano', 'Vermelho', 'Turbo', 14, 14, 'no_ponto', 1, 1, 3, NULL, NULL, 14.920424, -23.508929, '2026-08-10 23:50:04', '2026-08-09 19:56:22'),
(14, 3, 'ST - 90 - MM', 'hiace', 'ambos', 'Verde', 'Turbo', 14, 14, 'no_ponto', 1, 1, 6, NULL, NULL, 14.920424, -23.508929, '2026-08-10 20:30:22', '2026-08-10 20:14:52'),
(18, 12, 'ST - 22- RG', 'hiace', 'intermunicipal', 'Branco', 'Turbo', 14, 13, 'no_ponto', 1, 1, 6, 1, NULL, 14.920424, -23.508929, '2026-08-11 08:04:11', '2026-08-11 08:02:10');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_admin_criado_por` (`criado_por`);

--
-- Índices para tabela `alarmes_sos`
--
ALTER TABLE `alarmes_sos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sos_utilizador` (`utilizador_id`),
  ADD KEY `fk_sos_admin` (`resolvido_por`),
  ADD KEY `idx_sos_estado` (`estado`);

--
-- Índices para tabela `assentos_veiculo`
--
ALTER TABLE `assentos_veiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_assento_veiculo` (`veiculo_id`,`numero`),
  ADD KEY `fk_assento_reserva` (`reserva_id`);

--
-- Índices para tabela `avaliacoes_condutores`
--
ALTER TABLE `avaliacoes_condutores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_avaliacao_reserva` (`reserva_id`),
  ADD KEY `fk_avaliacao_condutor` (`condutor_id`),
  ADD KEY `fk_avaliacao_passageiro` (`passageiro_id`);

--
-- Índices para tabela `chamadas`
--
ALTER TABLE `chamadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chamada_remetente` (`remetente_id`),
  ADD KEY `idx_chamadas_participantes` (`destinatario_id`,`remetente_id`,`estado`);

--
-- Índices para tabela `comprovativos`
--
ALTER TABLE `comprovativos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comprovativo_condutor` (`condutor_id`),
  ADD KEY `fk_comprovativo_admin` (`revisto_por`);

--
-- Índices para tabela `comunicacoes_veiculo`
--
ALTER TABLE `comunicacoes_veiculo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comunicacao_remetente` (`remetente_id`),
  ADD KEY `fk_comunicacao_destinatario` (`destinatario_id`),
  ADD KEY `idx_comunicacoes_veiculo` (`veiculo_id`,`criado_em`);

--
-- Índices para tabela `config_precos`
--
ALTER TABLE `config_precos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`),
  ADD KEY `fk_config_precos_admin` (`atualizado_por`);

--
-- Índices para tabela `destinos_urbanos`
--
ALTER TABLE `destinos_urbanos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_destino_urbano_nome` (`nome`),
  ADD KEY `fk_destino_urbano_criador` (`criado_por`),
  ADD KEY `idx_destinos_urbanos_nome` (`nome`);

--
-- Índices para tabela `faturas`
--
ALTER TABLE `faturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referencia` (`referencia`),
  ADD KEY `fk_fatura_condutor` (`condutor_id`);

--
-- Índices para tabela `limites_cidades`
--
ALTER TABLE `limites_cidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cidade` (`cidade`),
  ADD KEY `fk_limite_admin` (`atualizado_por`);

--
-- Índices para tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_log_admin` (`admin_id`),
  ADD KEY `idx_logs_criado_em` (`criado_em`);

--
-- Índices para tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notificacao_remetente` (`remetente_id`),
  ADD KEY `idx_notificacoes_destinatario` (`destinatario_id`,`destinatario_tipo`,`lida`);

--
-- Índices para tabela `pacotes_pagamento`
--
ALTER TABLE `pacotes_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pacote_tipo_nome` (`tipo_servico`,`nome`),
  ADD KEY `fk_pacote_criado_por` (`criado_por`);

--
-- Índices para tabela `pagamentos_condutores`
--
ALTER TABLE `pagamentos_condutores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referencia` (`referencia`),
  ADD KEY `fk_pagcond_veiculo` (`veiculo_id`),
  ADD KEY `fk_pagcond_rota` (`rota_id`),
  ADD KEY `fk_pagcond_admin` (`aprovado_por`),
  ADD KEY `idx_pagcond_status` (`condutor_id`,`status`,`data_validade`),
  ADD KEY `fk_pagcond_pacote` (`pacote_id`);

--
-- Índices para tabela `parques`
--
ALTER TABLE `parques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_parque_admin` (`criado_por`);

--
-- Índices para tabela `pontos_partida`
--
ALTER TABLE `pontos_partida`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`),
  ADD KEY `fk_ponto_admin` (`criado_por`),
  ADD KEY `fk_ponto_aprovado_por` (`aprovado_por`);

--
-- Índices para tabela `precos_km`
--
ALTER TABLE `precos_km`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zona` (`zona`),
  ADD KEY `fk_preco_km_admin` (`atualizado_por`);

--
-- Índices para tabela `precos_rotas`
--
ALTER TABLE `precos_rotas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rota` (`ponto_origem_id`,`ponto_destino_id`),
  ADD KEY `fk_preco_rota_destino` (`ponto_destino_id`),
  ADD KEY `fk_preco_rota_admin` (`definido_por`);

--
-- Índices para tabela `proprietarios`
--
ALTER TABLE `proprietarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telefone` (`telefone`),
  ADD UNIQUE KEY `nif` (`nif`),
  ADD KEY `fk_proprietario_condutor` (`utilizador_condutor_id`);

--
-- Índices para tabela `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reserva_passageiro` (`passageiro_id`),
  ADD KEY `fk_reserva_veiculo` (`veiculo_id`),
  ADD KEY `fk_reserva_assento` (`assento_id`),
  ADD KEY `fk_reserva_ponto` (`ponto_partida_id`),
  ADD KEY `fk_reserva_destino` (`destino_id`),
  ADD KEY `idx_reservas_estado` (`estado`,`veiculo_id`);

--
-- Índices para tabela `sinalizacao_chamada`
--
ALTER TABLE `sinalizacao_chamada`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sinal_remetente` (`remetente_id`),
  ADD KEY `idx_sinal_chamada` (`chamada_id`,`id`);

--
-- Índices para tabela `sugestoes`
--
ALTER TABLE `sugestoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sugestao_utilizador` (`utilizador_id`),
  ADD KEY `fk_sugestao_condutor` (`condutor_id`),
  ADD KEY `idx_sugestoes_tipo_status` (`tipo`,`status`);

--
-- Índices para tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telefone` (`telefone`),
  ADD UNIQUE KEY `nif` (`nif`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_utilizadores_tipo_status` (`tipo`,`status`),
  ADD KEY `fk_utilizador_proprietario` (`proprietario_id`);

--
-- Índices para tabela `veiculos`
--
ALTER TABLE `veiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `fk_veiculo_condutor` (`condutor_id`),
  ADD KEY `fk_veiculo_ponto` (`ponto_partida_id`),
  ADD KEY `fk_veiculo_destino` (`destino_id`),
  ADD KEY `idx_veiculos_estado` (`estado`,`aprovado`,`ponto_partida_id`),
  ADD KEY `fk_veiculo_rota_fixa` (`rota_fixa_id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `alarmes_sos`
--
ALTER TABLE `alarmes_sos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `assentos_veiculo`
--
ALTER TABLE `assentos_veiculo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT de tabela `avaliacoes_condutores`
--
ALTER TABLE `avaliacoes_condutores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chamadas`
--
ALTER TABLE `chamadas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `comprovativos`
--
ALTER TABLE `comprovativos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `comunicacoes_veiculo`
--
ALTER TABLE `comunicacoes_veiculo`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `config_precos`
--
ALTER TABLE `config_precos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `destinos_urbanos`
--
ALTER TABLE `destinos_urbanos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `faturas`
--
ALTER TABLE `faturas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `limites_cidades`
--
ALTER TABLE `limites_cidades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `pacotes_pagamento`
--
ALTER TABLE `pacotes_pagamento`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `pagamentos_condutores`
--
ALTER TABLE `pagamentos_condutores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `parques`
--
ALTER TABLE `parques`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pontos_partida`
--
ALTER TABLE `pontos_partida`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `precos_km`
--
ALTER TABLE `precos_km`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `precos_rotas`
--
ALTER TABLE `precos_rotas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `proprietarios`
--
ALTER TABLE `proprietarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `sinalizacao_chamada`
--
ALTER TABLE `sinalizacao_chamada`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT de tabela `sugestoes`
--
ALTER TABLE `sugestoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `veiculos`
--
ALTER TABLE `veiculos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `administradores`
--
ALTER TABLE `administradores`
  ADD CONSTRAINT `fk_admin_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `alarmes_sos`
--
ALTER TABLE `alarmes_sos`
  ADD CONSTRAINT `fk_sos_admin` FOREIGN KEY (`resolvido_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sos_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `assentos_veiculo`
--
ALTER TABLE `assentos_veiculo`
  ADD CONSTRAINT `fk_assento_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assento_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `avaliacoes_condutores`
--
ALTER TABLE `avaliacoes_condutores`
  ADD CONSTRAINT `fk_avaliacao_condutor` FOREIGN KEY (`condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_avaliacao_passageiro` FOREIGN KEY (`passageiro_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_avaliacao_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `chamadas`
--
ALTER TABLE `chamadas`
  ADD CONSTRAINT `fk_chamada_destinatario` FOREIGN KEY (`destinatario_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chamada_remetente` FOREIGN KEY (`remetente_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `comprovativos`
--
ALTER TABLE `comprovativos`
  ADD CONSTRAINT `fk_comprovativo_admin` FOREIGN KEY (`revisto_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_comprovativo_condutor` FOREIGN KEY (`condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `comunicacoes_veiculo`
--
ALTER TABLE `comunicacoes_veiculo`
  ADD CONSTRAINT `fk_comunicacao_destinatario` FOREIGN KEY (`destinatario_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comunicacao_remetente` FOREIGN KEY (`remetente_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comunicacao_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `config_precos`
--
ALTER TABLE `config_precos`
  ADD CONSTRAINT `fk_config_precos_admin` FOREIGN KEY (`atualizado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `destinos_urbanos`
--
ALTER TABLE `destinos_urbanos`
  ADD CONSTRAINT `fk_destino_urbano_criador` FOREIGN KEY (`criado_por`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `faturas`
--
ALTER TABLE `faturas`
  ADD CONSTRAINT `fk_fatura_condutor` FOREIGN KEY (`condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `limites_cidades`
--
ALTER TABLE `limites_cidades`
  ADD CONSTRAINT `fk_limite_admin` FOREIGN KEY (`atualizado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD CONSTRAINT `fk_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notificacao_remetente` FOREIGN KEY (`remetente_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `pacotes_pagamento`
--
ALTER TABLE `pacotes_pagamento`
  ADD CONSTRAINT `fk_pacote_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `pagamentos_condutores`
--
ALTER TABLE `pagamentos_condutores`
  ADD CONSTRAINT `fk_pagcond_admin` FOREIGN KEY (`aprovado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pagcond_condutor` FOREIGN KEY (`condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pagcond_pacote` FOREIGN KEY (`pacote_id`) REFERENCES `pacotes_pagamento` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pagcond_rota` FOREIGN KEY (`rota_id`) REFERENCES `precos_rotas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pagcond_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `parques`
--
ALTER TABLE `parques`
  ADD CONSTRAINT `fk_parque_admin` FOREIGN KEY (`criado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `pontos_partida`
--
ALTER TABLE `pontos_partida`
  ADD CONSTRAINT `fk_ponto_admin` FOREIGN KEY (`criado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ponto_aprovado_por` FOREIGN KEY (`aprovado_por`) REFERENCES `administradores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `precos_km`
--
ALTER TABLE `precos_km`
  ADD CONSTRAINT `fk_preco_km_admin` FOREIGN KEY (`atualizado_por`) REFERENCES `administradores` (`id`);

--
-- Limitadores para a tabela `precos_rotas`
--
ALTER TABLE `precos_rotas`
  ADD CONSTRAINT `fk_preco_rota_admin` FOREIGN KEY (`definido_por`) REFERENCES `administradores` (`id`),
  ADD CONSTRAINT `fk_preco_rota_destino` FOREIGN KEY (`ponto_destino_id`) REFERENCES `pontos_partida` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_preco_rota_origem` FOREIGN KEY (`ponto_origem_id`) REFERENCES `pontos_partida` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `proprietarios`
--
ALTER TABLE `proprietarios`
  ADD CONSTRAINT `fk_proprietario_condutor` FOREIGN KEY (`utilizador_condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `reservas`
--
ALTER TABLE `reservas`
  ADD CONSTRAINT `fk_reserva_assento` FOREIGN KEY (`assento_id`) REFERENCES `assentos_veiculo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reserva_destino` FOREIGN KEY (`destino_id`) REFERENCES `pontos_partida` (`id`),
  ADD CONSTRAINT `fk_reserva_passageiro` FOREIGN KEY (`passageiro_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reserva_ponto` FOREIGN KEY (`ponto_partida_id`) REFERENCES `pontos_partida` (`id`),
  ADD CONSTRAINT `fk_reserva_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `sinalizacao_chamada`
--
ALTER TABLE `sinalizacao_chamada`
  ADD CONSTRAINT `fk_sinal_chamada` FOREIGN KEY (`chamada_id`) REFERENCES `chamadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sinal_remetente` FOREIGN KEY (`remetente_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `sugestoes`
--
ALTER TABLE `sugestoes`
  ADD CONSTRAINT `fk_sugestao_condutor` FOREIGN KEY (`condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sugestao_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD CONSTRAINT `fk_utilizador_proprietario` FOREIGN KEY (`proprietario_id`) REFERENCES `proprietarios` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `veiculos`
--
ALTER TABLE `veiculos`
  ADD CONSTRAINT `fk_veiculo_condutor` FOREIGN KEY (`condutor_id`) REFERENCES `utilizadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_veiculo_destino` FOREIGN KEY (`destino_id`) REFERENCES `pontos_partida` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_veiculo_ponto` FOREIGN KEY (`ponto_partida_id`) REFERENCES `pontos_partida` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_veiculo_rota_fixa` FOREIGN KEY (`rota_fixa_id`) REFERENCES `precos_rotas` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
