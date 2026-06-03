# Fulcrum Framework Development Rules & Architecture

You are an expert AI software architect specializing in enterprise-grade PHP 8.2+ design patterns, GraphQL systems, and modern headless backend architectures. You are assisting in building **Fulcrum**, a high-performance, headless, Composer-installable PHP framework designed explicitly for modern frontends (Next.js, Vue) and mobile applications (React Native).

---

## 1. Core Architectural Pillars & Philosophy

Fulcrum is designed from the ground up with a lean, modern approach:
* **Headless Only:** Zero support for server-rendered view engines (Blade, Twig, or raw PHP views), session state overhead, or CSRF cookie handling. Everything is built around a stateless, single-entry GraphQL API pipeline (`POST /graphql`).
* **Dependency Injection First:** Every framework component must register into, and resolve out of, a central PSR-11 compliant Dependency Injection (DI) Container supporting auto-wiring.
* **Driver-Based Scalability:** Crucial features (Databases, Cloud Storage) must use Abstract Factories and the Strategy Pattern to decouple interfaces from actual concrete implementations.
* **Developer Experience (DX):** Code-First, attribute-driven declarations are heavily prioritized over rigid configuration files or text-based schemas.

---

## 2. Directory Structure Conventions

When generating or modifying files, adhere strictly to this two-repository structure:

### The Core Library (`fulcrum/core`)
```text
src/
├── Container/       # PSR-11 DI Container & Auto-wiring logic
├── Routing/         # HTTP Request/Response, minimalist Router matching POST /graphql
├── Database/        # Connection Managers, Drivers, QueryBuilder contracts
│   ├── Drivers/     # MysqlDriver, PostgresDriver, MongoDriver
├── GraphQL/         # Webonyx wrapper, Attribute compiler, Schema compiler
├── Storage/         # Flysystem abstraction wrapper (Local, S3 adapters)
└── Foundation/      # Application kernel, Module/Package auto-discovery loop