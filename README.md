## Features

This php package implements the DTO feature. 
This abstract object lets you transfer your typed name data structures into known objects in a flash.
Other developers will thank you for using this feature :)

## Getting started

```bash
    composer require wearelaradev/dto
```

## Usage


### Simple Usage

Start by creating an object to represent your data structure. 
For the example, we'll choose to create a user with email and password properties

```php
<?php

use Laradev\Dto;

class UserDto extends Dto
{
    private string $password;
    public string $email;

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }
    
    public function getPassword(): string 
    {
        return $this->password;
    }
    

    public function toArray(): array
    {
        return [
            'password'  => $this->password,
            'email'     => $this->email,
        ];
    }
}

$user = UserDto::create([
    "password" => "Test1234",
    "email" => "fake@fake.com"
]);

echo $user->email // "fake@fake.com"
echo $userDto->toJson(); // {"password":"Test1234","email":"fake@fake.com"}
echo $userDto->toArray(); // ["password" => "Test1234","email" => "fake@fake.com"]
```

### Advanced Usage

Do you have complex data structures that may require object nesting? Then simply create another DTO!
Let's imagine that, when our user is created, the payload also requires structured data to create a profile. 
So we create a ProfileDto and add a profile property to our UserDto:

```php
<?php

use Laradev\Dto;

enum ProfileStatus: string
{
    case PUBLIC = "public";
    case PRIVATE = "private";
}

class ProfileDto extends Dto 
{
    public string $firstname;
    public string $lastname;
    public ProfileStatus $status;
    
    public function toArray(): array
    {
        return [
            "firstname" => $this->firstname,
            "lastname"  => $this->lastname,
            "status"    => $this->status->value
        ];
    }
}

class UserDto extends Dto 
{
    // ... LOGIC
    
    public ProfileDto $profile;
    
    // ... LOGIC
    
    public function toArray(): array
    {
        return [
            "password"  => $this->password,
            "email"     => $this->email,
            "profile"   => $this->profile->toArray()
        ];
    }
}

$user = UserDto::create([
    "password" => "Test1234",
    "email" => "fake@fake.com",
    "profile" => [
        "firstname" => "John",
        "lastname" => "Doe",
        "status" => "public"
    ]
]);

echo "{$user->profile->firstname} {$user->profile->lastname}" // John Doe
```

## Available methods
```php
/**
 * This method sets the object's properties from an array or another object. 
 * It uses reflection to detect object properties and initializes them if possible.
 */
static create(array $data): self

/**
 * This method sets the object's properties from an array. 
 * It uses reflection to detect object properties and initializes them if possible.
 */
fromArray(array $data): self

/**
 * This method sets the object's properties from another object. 
 * It uses reflection to detect object properties and initializes them if possible.
 */
fromObject(array $data): self

/**
 * This abstract method must be implemented in each child class to convert the object into an array.
 */
toArray(): array

/**
 * This method converts the object into JSON by calling toArray() and encoding the result in JSON.
 */
toJson(): string

/**
 * This method clone the current dto 
 */
clone(): self

/**
 * This method returns an array of property names that have been initialized via setData.
 */
getInitializedProperties(): array
```

## Additional information

If you encounter a bug or have any ideas for improvement, don't hesitate to send me a PR
or contact me via email at florian@laradev.ca :)
