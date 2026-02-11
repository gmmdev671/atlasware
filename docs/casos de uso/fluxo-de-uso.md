# Fluxos de Uso do Controle de Acesso

## 1. Definir Hierarquia de Cargos

1. Acessar tela de **Gestão de Roles**.
2. Cadastrar/ajustar roles principais:
   - `Master`
   - `gestor`
   - `colaborador`
   - `viewer`
3. Garantir que o mapeamento no código (`$roleNamesMap` no `AccessController`) reconheça corretamente esses nomes.

## 2. Atribuir Cargos Globais aos Usuários

1. Acessar tela **Cargos de Usuários** (`/admin/users`).
2. Para cada usuário:
   - Definir se é:
     - `Master`
     - `gestor`
     - `colaborador`
3. Resultado:
   - A tela **Visão Geral de Acesso** (`/admin/access`) passa a mostrar cada usuário na coluna correta.

## 3. Criar Times e Hierarquia de Times

1. Acessar **Gestão de Times** (`/admin/teams`).
2. Criar times raiz (ex: `Administrativo`, `Comercial`, `RH`, `Financeiro`).
3. Criar subtimes, definindo `time pai` (ex: `RH` como filho de `Administrativo`).

## 4. Definir Pacotes de Permissões por Time

1. Na lista de times, clicar em **Permissões** de um time.
2. Marcar as permissões que pertencem àquele time.
3. Opcionalmente, definir `até qual nível` essas permissões podem ser delegadas (campo `allowed_till_role_id`).

## 5. Gerenciar Membros de um Time

1. Na lista de times, clicar em **Membros** de um time.
2. Adicionar usuários ao time:
   - Escolher usuário.
   - Escolher cargo dentro do time (ex.: `gestor`, `colaborador`).
3. Remover membros quando necessário.

## 6. Interpretar a Visão Geral de Acesso (4 Colunas)

Na tela `/admin/access`:

- Coluna **Master**:
  - Exibe usuários com role `Master` ou `admin`.
- Coluna **Coordenação**:
  - Exibe usuários com role `gestor`.
- Coluna **Gerentes**:
  - (Reservada para role `gerente`, caso seja criada).
- Coluna **Funcionários**:
  - Exibe usuários com role `colaborador`.

Dentro de cada card:
- Nome do usuário.
- ID.
- Lista de times onde ele atua, com o cargo em cada time.