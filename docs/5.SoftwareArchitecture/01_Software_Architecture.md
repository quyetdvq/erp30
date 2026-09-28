# Software Architecture Specification

## Mini ERP Organization & Authorization System

## 1. Overview

Kiến trúc tổng thể hệ thống Mini ERP.

## 2. Architecture Layers

    Presentation Layer
            |
    Application Layer
            |
    Domain Service Layer
            |
    Data Access Layer
            |
    Database

## 3. Main Domains

-   Organization Management
-   Employee Management
-   Resource Authorization
-   Lifecycle Workflow
-   Audit

## 4. Authorization Architecture

    User
     |
    Employee
     |
    Employee Assignment
     |
    Organization Context
     |
    Profile Permission
     |
    Policy Evaluation
     |
    Decision

## 5. Technology Direction

-   Backend: CodeIgniter 4
-   Authentication: Shield
-   Database: MySQL/MariaDB
